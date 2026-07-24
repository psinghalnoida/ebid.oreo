<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;

class SetRecoveryEmail extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'set-recovery-email';
    protected $description = 'Sets a party\'s recovery email for dual-channel mPIN reset.';
    protected $usage        = 'set-recovery-email <mobile_number> [email]';

    public function run(array $params)
    {
        [$mobile, $email] = $params + [null, null];
        if (!$mobile) {
            CLI::error('Usage: php spark set-recovery-email <mobile_number> [email]');
            return;
        }
        $email = $email ?: 'psinghalnoida@gmail.com';

        $partyModel = new PartyModel();
        $party = $partyModel->findByMobile($mobile);
        if (!$party) {
            CLI::error("No registered party found with mobile number {$mobile}");
            return;
        }

        $partyModel->update($party['id'], ['recovery_email' => $email]);
        CLI::write("✓ Recovery email set to {$email} for {$mobile}", 'green');
        CLI::write('mPIN reset for this account now requires BOTH a mobile OTP and an email OTP together.', 'yellow');
    }
}
