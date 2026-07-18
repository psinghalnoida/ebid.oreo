<?php

namespace App\Models;

use CodeIgniter\Model;

class RatingEventModel extends Model
{
    protected $table            = 'rating_event';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'party_id', 'rating_role', 'event_type', 'previous_value', 'new_value',
        'reason', 'status', 'tenant_admin_approved_by', 'tenant_admin_approved_at',
        'super_admin_approved_by', 'super_admin_approved_at', 'appealed_at',
        'appeal_outcome', 'related_sale_event_id', 'applied_at',
    ];

    public function createEvent(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $data['applied_at'] = ($data['status'] ?? null) === 'applied' ? date('Y-m-d H:i:s') : null;
        $this->insert($data);
        return $this->find($id);
    }

    public function approveTenantAdmin(string $eventId, string $approverPartyId): array
    {
        $this->update($eventId, [
            'tenant_admin_approved_by' => $approverPartyId,
            'tenant_admin_approved_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($eventId);
    }

    public function approveSuperAdmin(string $eventId, string $approverPartyId): array
    {
        $this->update($eventId, [
            'super_admin_approved_by' => $approverPartyId,
            'super_admin_approved_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($eventId);
    }

    public function markApplied(string $eventId): array
    {
        $this->update($eventId, ['status' => 'applied', 'applied_at' => date('Y-m-d H:i:s')]);
        return $this->find($eventId);
    }

    // BR-39: count forced-neutral events against a party in a role
    public function countForcedNeutral(string $partyId, string $ratingRole): int
    {
        return $this->where('party_id', $partyId)
            ->where('rating_role', $ratingRole)
            ->where('event_type', 'forced_neutral')
            ->countAllResults();
    }
}
