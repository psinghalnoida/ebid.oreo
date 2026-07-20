<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyRoleModel extends Model
{
    protected $table            = 'party_role';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'role', 'tenant_id', 'revoked_at'];

    // BR-19/BR-09: role, optionally scoped to a tenant (NULL = global, e.g. buyer)
    public function grantRole(string $partyId, string $role, ?string $tenantId = null): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'party_id' => $partyId, 'role' => $role, 'tenant_id' => $tenantId]);
        return $this->find($id);
    }

    public function hasActiveRole(string $partyId, string $role, ?string $tenantId = null): bool
    {
        $builder = $this->where('party_id', $partyId)
            ->where('role', $role)
            ->where('revoked_at', null);
        $builder = $tenantId ? $builder->where('tenant_id', $tenantId) : $builder->where('tenant_id', null);
        return $builder->countAllResults() > 0;
    }

    // BR-44: exactly one active Tenant Admin per tenant — find who currently holds it
    public function findActiveTenantAdmin(string $tenantId): ?array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('role', 'tenant_admin')
            ->where('revoked_at', null)
            ->first();
    }

    // BR-44: promoting a new Tenant Admin auto-demotes the prior one
    public function promoteTenantAdmin(string $partyId, string $tenantId): array
    {
        $existing = $this->findActiveTenantAdmin($tenantId);
        if ($existing) {
            $this->update($existing['id'], ['revoked_at' => date('Y-m-d H:i:s')]);
        }
        return $this->grantRole($partyId, 'tenant_admin', $tenantId);
    }

    public function findActiveRolesForParty(string $partyId): array
    {
        return $this->where('party_id', $partyId)->where('revoked_at', null)->findAll();
    }
}
