<?php

namespace App\Libraries;

class AuditLogService
{
    private const GENESIS_HASH = 'ebidhub-audit-genesis-2026';
    private const ADVISORY_LOCK_KEY = 725001;

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function log(string $eventType, ?string $actorPartyId, array $payload, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $this->db->query('SELECT pg_advisory_lock(?)', [self::ADVISORY_LOCK_KEY]);

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
            $this->db->query('SELECT pg_advisory_unlock(?)', [self::ADVISORY_LOCK_KEY]);
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

            $occurredAt = (new \DateTimeImmutable($row['occurred_at']))->format('Y-m-d H:i:s.u');
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
