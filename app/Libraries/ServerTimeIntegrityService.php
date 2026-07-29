<?php

namespace App\Libraries;

use App\Models\ServerTimeCheckModel;

// Tech Stack §3.10 (Server Time Integrity): "All auction timing... is
// computed against a server clock synced to NTP against a verified
// time source, checked continuously. Any drift or manual clock
// adjustment beyond a defined tolerance triggers an automated alert to
// the Super Admin and is itself logged as an audit event."
//
// ⚠️ SCOPE NOTE: actually keeping the underlying OS clock synced to NTP
// (running an ntpd/chrony daemon) is a deployment/infrastructure
// concern, not something application code can do — no PHP process can
// force its own host's system clock to sync. What's built here is the
// application-layer half of the requirement: a real SNTP client (RFC
// 4330, no external vendor/account needed — same category as
// TotpService's real RFC 6238 implementation) that queries an
// authoritative external time source, computes the drift against this
// server's own clock, and raises an alert + audit event when it
// exceeds the configured tolerance.
//
// ⚠️ SANDBOX LIMITATION, CONFIRMED, NOT ASSUMED: this development
// environment's outbound network policy blocks both raw UDP (NTP is
// UDP/123) and arbitrary HTTPS egress (only an explicit allowlist of
// hosts is reachable through the proxy) — confirmed directly, not
// inferred, by testing both against pool.ntp.org and a public HTTPS
// time API and observing the connections rejected. This is the same
// category of external-dependency block as BR-46 (Gemini API key) and
// BR-52 (SabPaisa credentials), just for network-policy reasons
// instead of missing credentials. The SNTP client and drift-detection
// logic below are real and were verified against a real, protocol-
// correct SNTP round trip using a local test server standing in for a
// public NTP host (see D-84) — in a deployment where NTP/HTTPS egress
// isn't blocked, pointing DEFAULT_NTP_HOST at a real pool.ntp.org
// address works exactly the same way.
class ServerTimeIntegrityService
{
    public const DEFAULT_NTP_HOST = 'pool.ntp.org';
    private const NTP_PORT = 123;
    private const NTP_TIMEOUT_SECONDS = 3.0;
    private const DRIFT_TOLERANCE_DEFAULT = 5.0;

    private ServerTimeCheckModel $checkModel;

    public function __construct()
    {
        $this->checkModel = new ServerTimeCheckModel();
    }

    public function runCheck(?string $ntpHost = null, ?int $port = null): array
    {
        $host = $ntpHost ?? self::DEFAULT_NTP_HOST;
        $tolerance = SovereignRuleService::getNumeric(
            'TechStack-3.10.server_time_drift_tolerance_seconds', self::DRIFT_TOLERANCE_DEFAULT
        );

        $localTime = microtime(true);
        $ntpTime = $this->querySntp($host, $port ?? self::NTP_PORT, self::NTP_TIMEOUT_SECONDS);
        $reachable = $ntpTime !== null;
        $drift = $reachable ? abs($localTime - $ntpTime) : null;
        $withinTolerance = $reachable ? ($drift <= $tolerance) : null;

        $checkId = Uuid::v4();
        $this->checkModel->insert([
            'id' => $checkId,
            'ntp_host' => $host,
            'ntp_reachable' => $reachable,
            'local_time' => date('Y-m-d H:i:s', (int) $localTime),
            'ntp_time' => $reachable ? date('Y-m-d H:i:s', (int) $ntpTime) : null,
            'drift_seconds' => $drift,
            'tolerance_seconds' => $tolerance,
            'within_tolerance' => $withinTolerance,
        ]);

        if ($reachable && !$withinTolerance) {
            (new AuditLogService())->log('server_time.drift_alert', null, [
                'checkId' => $checkId, 'ntpHost' => $host,
                'driftSeconds' => $drift, 'toleranceSeconds' => $tolerance,
            ]);
        }

        return self::normalizeBooleans($this->checkModel->find($checkId));
    }

    public function acknowledge(string $checkId, string $acknowledgedByPartyId): array
    {
        $check = $this->checkModel->find($checkId);
        if (!$check) {
            throw new \RuntimeException('Server time check not found.');
        }
        $this->checkModel->update($checkId, [
            'acknowledged_at' => date('Y-m-d H:i:s'), 'acknowledged_by_party_id' => $acknowledgedByPartyId,
        ]);
        (new AuditLogService())->log('server_time.drift_alert_acknowledged', $acknowledgedByPartyId, ['checkId' => $checkId]);
        return self::normalizeBooleans($this->checkModel->find($checkId));
    }

    // Postgres/CI4 returns boolean columns as the strings "t"/"f", not
    // native PHP bool -- both are PHP-truthy, so leaving them as-is
    // would silently break any `!$row['within_tolerance']`-style check
    // by every caller. Normalized once here, at the service boundary,
    // rather than requiring every consumer to know this quirk.
    private static function normalizeBooleans(array $row): array
    {
        foreach (['ntp_reachable', 'within_tolerance'] as $field) {
            if ($row[$field] === null) {
                continue;
            }
            $row[$field] = in_array($row[$field], [true, 't', 1, '1'], true);
        }
        return $row;
    }

    // Real, minimal SNTP client (RFC 4330 client mode). Returns the
    // remote server's reported Unix timestamp, or null if the host is
    // unreachable/times out/returns a malformed response.
    private function querySntp(string $host, int $port, float $timeoutSeconds): ?float
    {
        $sock = @stream_socket_client("udp://{$host}:{$port}", $errno, $errstr, $timeoutSeconds);
        if (!$sock) {
            return null;
        }
        stream_set_timeout($sock, (int) ceil($timeoutSeconds));

        // 48-byte NTP packet, all zero except the first byte:
        // LI=0 (no warning), VN=3 (NTPv3), Mode=3 (client).
        $packet = str_repeat("\0", 48);
        $packet[0] = chr(0x1B);
        if (@fwrite($sock, $packet) === false) {
            fclose($sock);
            return null;
        }

        $response = fread($sock, 48);
        $meta = stream_get_meta_data($sock);
        fclose($sock);

        if ($meta['timed_out'] || $response === false || strlen($response) < 48) {
            return null;
        }

        // Transmit Timestamp field: bytes 40-43 seconds (since the NTP
        // epoch, 1900-01-01), bytes 44-47 fractional seconds, both
        // big-endian 32-bit unsigned.
        $seconds = unpack('N', substr($response, 40, 4))[1];
        $fraction = unpack('N', substr($response, 44, 4))[1];

        $ntpToUnixEpochOffset = 2208988800; // seconds between 1900-01-01 and 1970-01-01
        return ($seconds - $ntpToUnixEpochOffset) + ($fraction / 4294967296);
    }
}
