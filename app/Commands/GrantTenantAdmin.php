<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\PartyRoleModel;

// Interim CLI bootstrap tool. No Super Admin panel exists yet to grant
// this role through the UI — this command exists so Tenant Admins can be
// provisioned at all until that's built. BR-44: promoting a new Tenant
// Admin auto-demotes whoever previously held it for that tenant.
class GrantTenantAdmin extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'grant:tenant-admin';
    protected $description = 'Grants the tenant_admin role to a registered party for a given tenant.';
    protected $usage        = 'grant:tenant-admin <mobile_number> <tenant_id>';

    public function run(array $params)
    {
        [$mobile, $tenantId] = $params + [null, null];
        if (!$mobile || !$tenantId) {
            CLI::error('Usage: php spark grant:tenant-admin <mobile_number> <tenant_id>');
            return;
        }

        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $roleModel = new PartyRoleModel();

        $party = $partyModel->findByMobile($mobile);
        if (!$party) {
            CLI::error("No registered party found with mobile number {$mobile}");
            return;
        }

        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            CLI::error("No tenant found with id {$tenantId}");
            return;
        }

        $existing = $roleModel->findActiveTenantAdmin($tenantId);
        if ($existing) {
            CLI::write("Note: this will demote the current Tenant Admin (party {$existing['party_id']}) per BR-44.", 'yellow');
        }

        $roleModel->promoteTenantAdmin($party['id'], $tenantId);
        CLI::write("✓ Granted tenant_admin to {$mobile} (party {$party['id']}) for tenant \"{$tenant['name']}\" ({$tenantId})", 'green');
    }
}
