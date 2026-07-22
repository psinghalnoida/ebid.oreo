<?php

namespace App\Models;

use CodeIgniter\Model;

class SellerApplicationModel extends Model
{
    protected $table            = 'seller_application';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'tenant_id', 'status', 'rejection_reason', 'decided_by_party_id', 'decided_at'];

    public function createApplication(string $partyId, string $tenantId): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'party_id' => $partyId, 'tenant_id' => $tenantId, 'status' => 'pending']);
        return $this->find($id);
    }

    public function findForPartyAndTenant(string $partyId, string $tenantId): ?array
    {
        return $this->where('party_id', $partyId)->where('tenant_id', $tenantId)->first();
    }

    public function findPendingForTenant(string $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->where('status', 'pending')->findAll();
    }
}
