<?php

namespace App\Models;

use CodeIgniter\Model;

// BR-62/64: Tenant-level OAuth2-shaped API credentials.
class TenantApiCredentialModel extends Model
{
    protected $table            = 'tenant_api_credential';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'tenant_id', 'client_id', 'client_secret_hash', 'status',
        'created_by_party_id', 'revoked_at', 'last_used_at',
    ];

    public function createCredential(string $tenantId, string $clientId, string $secretHash, ?string $createdByPartyId): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'client_id' => $clientId,
            'client_secret_hash' => $secretHash, 'created_by_party_id' => $createdByPartyId,
        ]);
        return $this->find($id);
    }

    public function findActiveByClientId(string $clientId): ?array
    {
        return $this->where('client_id', $clientId)->where('status', 'active')->first();
    }

    public function findForTenant(string $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('created_at', 'DESC')->findAll();
    }

    public function revoke(string $id): array
    {
        $this->update($id, ['status' => 'revoked', 'revoked_at' => date('Y-m-d H:i:s')]);
        return $this->find($id);
    }

    public function touchLastUsed(string $id): void
    {
        $this->update($id, ['last_used_at' => date('Y-m-d H:i:s')]);
    }
}
