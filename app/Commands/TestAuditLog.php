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
        $grants = $db->query("SELECT privilege_type FROM information_schema.role_table_grants WHERE table_name='audit_log' AND grantee='ebidhub_app'")->getResultArray();
        $privileges = array_column($grants, 'privilege_type');
        $this->assert(!in_array('UPDATE', $privileges, true), 'UPDATE is genuinely not granted to the app database role');
        $this->assert(!in_array('DELETE', $privileges, true), 'DELETE is genuinely not granted to the app database role');
        $this->assert(!in_array('TRUNCATE', $privileges, true), 'TRUNCATE is genuinely not granted to the app database role');
        $this->assert(in_array('INSERT', $privileges, true), 'INSERT is correctly still allowed');
        $this->assert(in_array('SELECT', $privileges, true), 'SELECT is correctly still allowed');

        CLI::write("\n=== Actually attempting an UPDATE fails at the database level ===", 'yellow');
        $updateBlocked = false;
        try {
            $db->query("UPDATE audit_log SET event_type = 'tampered' WHERE 1=1");
        } catch (\Throwable $e) {
            $updateBlocked = str_contains(strtolower($e->getMessage()), 'permission denied');
        }
        $this->assert($updateBlocked, 'A real UPDATE attempt genuinely fails with a permission error');

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
        // Tamper as the postgres superuser via a SQL file + direct shell
        // call — this genuinely bypasses the application entirely,
        // exactly the threat model BR-05 describes (someone with raw
        // database access, not going through the app's own connection).
        $tamperSqlPath = sys_get_temp_dir() . '/audit_tamper_test.sql';
        file_put_contents($tamperSqlPath, "UPDATE audit_log SET payload = '{\"detail\": \"TAMPERED\"}' WHERE id = '{$r2['id']}';");
        exec('su postgres -c "psql -d ebidhub_ci4 -f ' . escapeshellarg($tamperSqlPath) . '" 2>&1', $tamperOutput, $tamperReturn);
        $tamperSucceeded = $tamperReturn === 0 && str_contains(implode(' ', $tamperOutput), 'UPDATE 1');
        $this->assert($tamperSucceeded, 'The tampering attempt itself succeeded (bypassing the app entirely, as a raw superuser) — setup for the real test below: ' . implode(' ', $tamperOutput));

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
