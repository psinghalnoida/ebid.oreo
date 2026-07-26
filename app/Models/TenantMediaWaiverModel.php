<?php

namespace App\Models;

use CodeIgniter\Model;

class TenantMediaWaiverModel extends Model
{
    protected $table            = 'tenant_media_waiver';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields = [
        'id', 'tenant_id', 'category', 'business_justification', 'status',
        'requested_by_party_id', 'decided_by_party_id', 'decision_rationale',
        'granted_at', 'expires_at', 'revoked_reason', 'updated_at',
    ];

    public function findActiveForTenantCategory(string $tenantId, string $category): ?array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('category', $category)
            ->where('status', 'approved')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    public function findPending(): array
    {
        return $this->where('status', 'pending')->findAll();
    }
}
