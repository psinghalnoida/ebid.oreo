<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\ServerTimeIntegrityService;
use App\Libraries\SchedulerService;
use App\Libraries\SovereignRuleService;
use App\Models\ServerTimeCheckModel;
use App\Models\PartyModel;

// Tech Stack §3.10 (Server Time Integrity). Public NTP (UDP/123) is
// unreachable from this sandbox's outbound network policy (confirmed,
// not assumed -- see ServerTimeIntegrityService's class doc block), so
// this exercises the real SNTP client against a real, protocol-correct
// local SNTP responder standing in for a public NTP host, rather than
// mocking the client's own parsing logic. The wire format, socket I/O,
// and drift arithmetic under test are the exact same code that runs
// against a real pool.ntp.org host in an unrestricted deployment.
class TestServerTimeCheck extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:servertimecheck';
    protected $description = 'Server Time Integrity (Tech Stack §3.10): real SNTP client + drift alert cascade.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $service = new ServerTimeIntegrityService();
        $checkModel = new ServerTimeCheckModel();
        $db = \Config\Database::connect();

        $party = (new PartyModel())->createParty('+919' . random_int(100000000, 999999999));
        $superAdminId = $party['id'];

        CLI::write("\n=== Step 1: an unreachable NTP host is handled honestly, not silently swallowed ===", 'yellow');
        $unreachable = $service->runCheck('192.0.2.1', 9); // TEST-NET-1 (RFC 5737) -- guaranteed non-routable
        $this->assert($unreachable['ntp_reachable'] === false, 'ntp_reachable correctly false for an unreachable host');
        $this->assert($unreachable['drift_seconds'] === null, 'No drift figure fabricated when unreachable');
        $this->assert($unreachable['within_tolerance'] === null, 'within_tolerance correctly null, not falsely true or false');
        $auditCountBefore = $db->table('audit_log')->where('event_type', 'server_time.drift_alert')->countAllResults();

        CLI::write("\n=== Step 2: real SNTP protocol round trip against a local, protocol-correct responder ===", 'yellow');
        $port = random_int(20000, 60000);
        $now = microtime(true);
        [$serverProc, $serverPipes] = $this->startTestSntpServer($port, $now);
        usleep(300000);

        $inTolerance = $service->runCheck('127.0.0.1', $port);
        $this->stopTestSntpServer($serverProc, $serverPipes);

        $this->assert($inTolerance['ntp_reachable'] === true, 'Real SNTP round trip succeeded against the local responder');
        $this->assert((float) $inTolerance['drift_seconds'] < 0.5, 'Drift against a same-instant responder is genuinely near-zero (got ' . $inTolerance['drift_seconds'] . 's)');
        $this->assert($inTolerance['within_tolerance'] === true, 'Near-zero drift is correctly within tolerance');
        $auditCountAfterInTolerance = $db->table('audit_log')->where('event_type', 'server_time.drift_alert')->countAllResults();
        $this->assert($auditCountAfterInTolerance === $auditCountBefore, 'No drift alert logged when within tolerance');

        CLI::write("\n=== Step 3: drift beyond tolerance genuinely triggers the alert + audit event ===", 'yellow');
        $tolerance = SovereignRuleService::getNumeric('TechStack-3.10.server_time_drift_tolerance_seconds', 5.0);
        $port2 = random_int(20000, 60000);
        $driftedTimestamp = $now - ($tolerance + 100); // guaranteed well beyond tolerance
        [$serverProc2, $serverPipes2] = $this->startTestSntpServer($port2, $driftedTimestamp);
        usleep(300000);

        $drifted = $service->runCheck('127.0.0.1', $port2);
        $this->stopTestSntpServer($serverProc2, $serverPipes2);

        $this->assert($drifted['ntp_reachable'] === true, 'Reachable against the drifted responder');
        $this->assert((float) $drifted['drift_seconds'] > $tolerance, 'Drift genuinely exceeds tolerance (got ' . $drifted['drift_seconds'] . 's vs tolerance ' . $tolerance . 's)');
        $this->assert($drifted['within_tolerance'] === false, 'within_tolerance correctly false');

        $alertRow = $db->table('audit_log')->where('event_type', 'server_time.drift_alert')
            ->orderBy('sequence_number', 'DESC')->get()->getRowArray();
        $this->assert($alertRow !== null, 'A server_time.drift_alert audit entry was genuinely written');
        $payload = json_decode($alertRow['payload'], true);
        $this->assert(($payload['checkId'] ?? null) === $drifted['id'], 'The audit entry references the real check row, not a placeholder');

        CLI::write("\n=== Step 4: acknowledging clears it from the unacknowledged list ===", 'yellow');
        $unackBefore = $checkModel->findUnacknowledgedDriftAlerts();
        $this->assert(in_array($drifted['id'], array_column($unackBefore, 'id'), true), 'The drift alert appears in the unacknowledged list before acknowledgement');

        $service->acknowledge($drifted['id'], $superAdminId);
        $unackAfter = $checkModel->findUnacknowledgedDriftAlerts();
        $this->assert(!in_array($drifted['id'], array_column($unackAfter, 'id'), true), 'The drift alert no longer appears in the unacknowledged list after acknowledgement');

        $ackEntry = $db->table('audit_log')->where('event_type', 'server_time.drift_alert_acknowledged')
            ->orderBy('sequence_number', 'DESC')->get()->getRowArray();
        $this->assert($ackEntry !== null && $ackEntry['actor_party_id'] === $superAdminId, 'Acknowledgement is genuinely audit-logged with the real actor');

        CLI::write("\n=== Step 5: SchedulerService::runAll() genuinely includes the drift check on every pass ===", 'yellow');
        $result = (new SchedulerService())->runAll();
        $this->assert(array_key_exists('serverTimeDriftAlerts', $result), 'runAll() includes serverTimeDriftAlerts in its summary');
        $countBefore = $checkModel->countAllResults();
        (new SchedulerService())->runAll();
        $countAfter = $checkModel->countAllResults();
        $this->assert($countAfter === $countBefore + 1, 'Each runAll() pass genuinely records one more server_time_check row (checked continuously)');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function startTestSntpServer(int $port, float $embeddedUnixTime): array
    {
        $script = <<<'PHP'
            <?php
            $port = (int) $argv[1];
            $fakeUnixTime = (float) $argv[2];
            $sock = stream_socket_server("udp://127.0.0.1:{$port}", $errno, $errstr, STREAM_SERVER_BIND);
            if (!$sock) { fwrite(STDERR, "bind failed\n"); exit(1); }
            $request = stream_socket_recvfrom($sock, 48, 0, $peer);
            if ($request === false || strlen($request) < 48) { exit(1); }
            $ntpToUnixEpochOffset = 2208988800;
            $ntpSeconds = (int) floor($fakeUnixTime) + $ntpToUnixEpochOffset;
            $ntpFraction = (int) round((($fakeUnixTime - floor($fakeUnixTime)) * 4294967296));
            $response = str_repeat("\0", 48);
            $response[0] = chr(0x1C);
            $response = substr_replace($response, pack('N', $ntpSeconds), 40, 4);
            $response = substr_replace($response, pack('N', $ntpFraction), 44, 4);
            stream_socket_sendto($sock, $response, 0, $peer);
            PHP;
        $scriptPath = sys_get_temp_dir() . '/sntp_test_responder_' . $port . '.php';
        file_put_contents($scriptPath, $script);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(['php', $scriptPath, (string) $port, (string) $embeddedUnixTime], $descriptors, $pipes);
        return [$proc, ['pipes' => $pipes, 'scriptPath' => $scriptPath]];
    }

    private function stopTestSntpServer($proc, array $meta): void
    {
        usleep(200000);
        if (is_resource($proc)) {
            proc_terminate($proc);
            proc_close($proc);
        }
        foreach ($meta['pipes'] as $p) {
            if (is_resource($p)) fclose($p);
        }
        @unlink($meta['scriptPath']);
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  ✓ {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  ✗ {$msg}", 'red');
        }
    }
}
