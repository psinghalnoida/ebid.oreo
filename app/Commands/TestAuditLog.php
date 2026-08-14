<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Libraries\AuditLogService;

class TestAuditLog extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:auditlog';
    protected $description = 'Runs the hash-chained audit log against real data, including deliberate tampering.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $audit = new AuditLogService();
        $db = \Config\Database::connect();

        CLI::write('=== Database-level lockdown ===', 'yellow');
        // MySQL migration (D-124+): the REVOKE UPDATE/DELETE/TRUNCATE ...
        // GRANT INSERT/SELECT privilege lockdown this section originally
        // verified is NOT reproduced on MySQL -- confirmed empirically not
        // achievable there (MySQL's partial_revokes only carves a
        // database-level restriction out of a global grant, never a
        // table-level restriction out of a database-level grant; see
        // CreateAuditLog.php's up() for the full reasoning). ebidhub_app
        // genuinely retains UPDATE/DELETE on audit_log under MySQL -- a
        // real, accepted, documented limitation (docs/DECISIONS.md).
        //
        // This section therefore no longer attempts the destructive
        // "UPDATE ... WHERE 1=1, expect it to be blocked" probe the
        // Postgres version used: on MySQL that UPDATE would actually
        // succeed and corrupt every existing audit_log row (including
        // ones written by other test suites sharing this database),
        // which is exactly the kind of self-inflicted damage a real
        // regression run must not risk. What MySQL retains, and what the
        // rest of this suite verifies below, is tamper-EVIDENCE: the
        // hash chain still catches any tampering after the fact,
        // regardless of which privileges the connection holds.
        CLI::write('  (skipped on MySQL -- privilege-based lockdown not available; see comment above)', 'yellow');

        CLI::write("\n=== Real log entries chain correctly ===", 'yellow');
        $party = $partyModel->createParty('+919888701001');

        $r1 = $audit->log('test.event.one', $party['id'], ['detail' => 'first event'], '127.0.0.1', 'TestAgent/1.0');
        $r2 = $audit->log('test.event.two', $party['id'], ['detail' => 'second event'], '127.0.0.1', 'TestAgent/1.0');
        $r3 = $audit->log('test.event.three', null, ['detail' => 'system event, no actor'], null, null);

        $row2 = $db->table('audit_log')->where('id', $r2['id'])->get()->getRowArray();
        $this->assert($row2['previous_hash'] === $r1['recordHash'], 'Record 2 correctly chains from record 1\'s actual hash');

        $row3 = $db->table('audit_log')->where('id', $r3['id'])->get()->getRowArray();
        $this->assert($row3['previous_hash'] === $r2['recordHash'], 'Record 3 correctly chains from record 2\'s actual hash');
        $this->assert($row3['actor_party_id'] === null, 'A system event with no actor is correctly recorded as such');

        CLI::write("\n=== A clean chain verifies with no tampering detected ===", 'yellow');
        $cleanResult = $audit->verifyChainIntegrity();
        $this->assert($cleanResult === null, 'verifyChainIntegrity() correctly reports null (clean) before any tampering');

        CLI::write("\n=== Deliberately tamper with a record via SQL (simulating a compromised raw DB credential) ===", 'yellow');
        // Tamper via a direct shell call to the mysql CLI, entirely outside
        // this PHP process's own DB connection -- genuinely bypasses the
        // application, exactly the threat model BR-05 describes (someone
        // with raw database access, not going through the app's own
        // connection). Targets this test's own record 2 by id specifically
        // (not a blanket WHERE 1=1) so it can never touch rows belonging
        // to any other suite sharing this database.
        $dbConfig = (new \Config\Database())->default;
        $tamperSqlPath = sys_get_temp_dir() . '/audit_tamper_test.sql';
        file_put_contents($tamperSqlPath, "UPDATE audit_log SET payload = '{\"detail\": \"TAMPERED\"}' WHERE id = '{$r2['id']}';");
        $tamperCmd = sprintf(
            'mysql -h %s -P %d -u %s -p%s %s < %s 2>&1',
            escapeshellarg($dbConfig['hostname']),
            (int) $dbConfig['port'],
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($tamperSqlPath)
        );
        exec($tamperCmd, $tamperOutput, $tamperReturn);
        $rowAfterTamper = $db->table('audit_log')->where('id', $r2['id'])->get()->getRowArray();
        $tamperSucceeded = $tamperReturn === 0 && str_contains($rowAfterTamper['payload'], 'TAMPERED');
        $this->assert($tamperSucceeded, 'The tampering attempt itself succeeded (bypassing the app entirely, via a raw DB connection) — setup for the real test below: ' . implode(' ', $tamperOutput));

        CLI::write("\n=== The tampering is now genuinely detected by re-walking the chain ===", 'yellow');
        $tamperedResult = $audit->verifyChainIntegrity();
        $row2After = $db->table('audit_log')->where('id', $r2['id'])->get()->getRowArray();
        $this->assert($tamperedResult !== null, 'verifyChainIntegrity() correctly detects the chain is now broken');
        $this->assert((int) $tamperedResult === (int) $row2After['sequence_number'], 'The detected break correctly points at the exact tampered record');

        CLI::write("\n=== Concurrent rapid-fire writes do not fork the chain ===", 'yellow');
        $rapidHashes = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $audit->log('test.event.rapid', $party['id'], ['i' => $i]);
            $rapidHashes[] = $r['recordHash'];
        }
        $rapidRows = $db->table('audit_log')->whereIn('record_hash', $rapidHashes)->orderBy('sequence_number', 'ASC')->get()->getResultArray();
        $chainedCorrectly = true;
        for ($i = 1; $i < count($rapidRows); $i++) {
            if ($rapidRows[$i]['previous_hash'] !== $rapidRows[$i - 1]['record_hash']) {
                $chainedCorrectly = false;
                break;
            }
        }
        $this->assert($chainedCorrectly, '10 rapid sequential writes all chain correctly, no forks or gaps');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
