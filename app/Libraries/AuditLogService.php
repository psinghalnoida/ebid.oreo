<?php

namespace App\Libraries;

class AuditLogService
{
    private const GENESIS_HASH = 'ebidhub-audit-genesis-2026';

    // MySQL's GET_LOCK()/RELEASE_LOCK() take a string name, not the numeric
    // key Postgres's pg_advisory_lock()/pg_advisory_unlock() used.
    private const ADVISORY_LOCK_NAME = 'ebidhub_audit_log_chain';

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function log(string $eventType, ?string $actorPartyId, array $payload, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        // -1 timeout = block indefinitely, matching pg_advisory_lock()'s
        // original (non-timing-out) blocking behavior.
        $this->db->query('SELECT GET_LOCK(?, -1)', [self::ADVISORY_LOCK_NAME]);

        try {
            $previousHash = $this->getLastHash();
            $occurredAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

            $canonical = json_encode([
                'occurred_at' => $occurredAt,
                'event_type' => $eventType,
                'actor_party_id' => $actorPartyId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'payload' => $payload,
            ], JSON_UNESCAPED_SLASHES);

            $recordHash = hash('sha256', $previousHash . $canonical);

            $id = Uuid::v4();
            $this->db->table('audit_log')->insert([
                'id' => $id,
                'occurred_at' => $occurredAt,
                'occurred_at_canonical' => $occurredAt,
                'event_type' => $eventType,
                'actor_party_id' => $actorPartyId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'previous_hash' => $previousHash,
                'record_hash' => $recordHash,
            ]);

            return ['id' => $id, 'recordHash' => $recordHash];
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [self::ADVISORY_LOCK_NAME]);
        }
    }

    private function getLastHash(): string
    {
        $last = $this->db->table('audit_log')
            ->select('record_hash')
            ->orderBy('sequence_number', 'DESC')
            ->limit(1)
            ->get()->getRowArray();
        return $last['record_hash'] ?? self::GENESIS_HASH;
    }

    public function verifyChainIntegrity(): ?int
    {
        $rows = $this->db->table('audit_log')->orderBy('sequence_number', 'ASC')->get()->getResultArray();

        $expectedPreviousHash = self::GENESIS_HASH;
        foreach ($rows as $row) {
            if ($row['previous_hash'] !== $expectedPreviousHash) {
                return (int) $row['sequence_number'];
            }

            // Use the exact stored canonical string, not a re-derivation
            // via DateTimeImmutable round-tripped through the TIMESTAMPTZ
            // column — Postgres trims trailing zero fractional digits on
            // storage, so re-deriving would not reliably reproduce the
            // original hash input (found and fixed during D-45).
            $occurredAt = $row['occurred_at_canonical'] ?? (new \DateTimeImmutable($row['occurred_at']))->format('Y-m-d H:i:s.u');
            $canonical = json_encode([
                'occurred_at' => $occurredAt,
                'event_type' => $row['event_type'],
                'actor_party_id' => $row['actor_party_id'],
                'ip_address' => $row['ip_address'],
                'user_agent' => $row['user_agent'],
                'payload' => json_decode($row['payload'], true),
            ], JSON_UNESCAPED_SLASHES);

            $expectedHash = hash('sha256', $row['previous_hash'] . $canonical);
            if ($expectedHash !== $row['record_hash']) {
                return (int) $row['sequence_number'];
            }

            $expectedPreviousHash = $row['record_hash'];
        }

        return null;
    }
}
