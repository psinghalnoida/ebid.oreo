<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Libraries\AuthorizationService;

class ResetTotp extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'reset-totp';
    protected $description = 'Clears a Super Admin\'s TOTP secret, forcing re-enrollment on next login.';
    protected $usage        = 'reset-totp <mobile_number>';

    public function run(array $params)
    {
        [$mobile] = $params + [null];
        if (!$mobile) {
            CLI::error('Usage: php spark reset-totp <mobile_number>');
            return;
        }

        $partyModel = new PartyModel();
        $party = $partyModel->findByMobile($mobile);
        if (!$party) {
            CLI::error("No registered party found with mobile number {$mobile}");
            return;
        }

        $auth = new AuthorizationService();
        if (!$auth->isSuperAdmin($party['id'])) {
            CLI::error("{$mobile} does not hold the super_admin role — nothing to reset.");
            return;
        }

        $partyModel->update($party['id'], ['totp_secret' => null, 'totp_enabled_at' => null]);
        CLI::write("✓ TOTP cleared for {$mobile}.", 'green');
        CLI::write('They must log in normally, then visit /admin/setup-totp to enroll a new authenticator.', 'yellow');
    }
}
