<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\PartyRoleModel;

// ⚠️ MINIMAL STAND-IN, same as grant:tenant-admin — grants role
// membership only. This is NOT BR-04's real Super Admin login (separate
// Auth0/TOTP path), which remains deferred to Tier 3 (D-23). This exists
// purely to unblock BR-40's Dispute Resolution Framework, which requires
// a Super Admin concept to exist at all.
class GrantSuperAdmin extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'grant:super-admin';
    protected $description = 'Grants the super_admin role to a registered party (global, not tenant-scoped).';
    protected $usage        = 'grant:super-admin <mobile_number>';

    public function run(array $params)
    {
        [$mobile] = $params + [null];
        if (!$mobile) {
            CLI::error('Usage: php spark grant:super-admin <mobile_number>');
            return;
        }

        $partyModel = new PartyModel();
        $roleModel = new PartyRoleModel();

        $party = $partyModel->findByMobile($mobile);
        if (!$party) {
            CLI::error("No registered party found with mobile number {$mobile}");
            return;
        }

        if ($roleModel->hasActiveRole($party['id'], 'super_admin', null)) {
            CLI::write("{$mobile} already has the super_admin role.", 'yellow');
            return;
        }

        $roleModel->grantRole($party['id'], 'super_admin', null);
        CLI::write("✓ Granted super_admin to {$mobile} (party {$party['id']})", 'green');
    }
}
