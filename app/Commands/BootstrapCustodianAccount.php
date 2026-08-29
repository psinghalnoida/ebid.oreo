<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\PartyRoleModel;
use App\Models\SuperAdminCredentialModel;
use App\Libraries\AuthService;
use App\Libraries\AuditLogService;

// Project owner's explicit request: a known, working Custodian (Super
// Admin) account for the live system, seeded with a specific email and
// password rather than going through the normal self-registration flow.
// Deliberately implemented as a real, bcrypt-hashed database record
// created by a controlled server-side command — NOT as a literal
// credential comparison inside the login path itself. The latter would
// be a genuine backdoor (a bypass baked into the auth logic that no
// audit of app/Libraries/SuperAdminAuthService.php would ever remove);
// this instead produces a completely ordinary party + super_admin_credential
// pair that goes through the exact same email/password/TOTP verification
// as any other Custodian account. `password_verify()` in
// SuperAdminAuthService::login() has no idea this party was created by a
// CLI command instead of a self-registration form.
//
// The email and password default to exactly what the project owner
// specified, so `php spark bootstrap:custodian` with no arguments
// reproduces that account. Both are overridable arguments precisely so
// this literal password doesn't have to be the one actually in use
// forever — change it via the forgot-password flow once, ordinary
// session, then re-run this command with different arguments if a
// different value is wanted later. Re-running is always safe: this
// updates the email/password on an existing account rather than
// erroring, so it doubles as a "reset this account back to its known
// bootstrap state" command.
//
// A mobile_number is still required, unchanged, because `party` (the
// one identity record shared by every role) is keyed on it — the
// Custodian's email/password login lives in the separate
// super_admin_credential table (see CreateSuperAdminCredential migration)
// layered on top of that same party row, not a replacement for it.
class BootstrapCustodianAccount extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'bootstrap:custodian';
    protected $description = 'Creates or resets the project owner\'s known Custodian (Super Admin) account.';
    protected $usage        = 'bootstrap:custodian [email] [password] [mobile_number]';

    public function run(array $params)
    {
        $email = $params[0] ?? 'psinghalnoida@gmail.com';
        $password = $params[1] ?? 'ChangeMe#4148';
        $mobile = $params[2] ?? '+919811047785';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error("Invalid email: {$email}");
            return;
        }
        if (strlen($password) < 8) {
            CLI::error('Password must be at least 8 characters.');
            return;
        }
        if (!AuthService::isValidIndianMobile($mobile)) {
            CLI::error("Invalid mobile number format: {$mobile} (expected +91XXXXXXXXXX)");
            return;
        }

        $partyModel = new PartyModel();
        $roleModel = new PartyRoleModel();
        $credentialModel = new SuperAdminCredentialModel();
        $audit = new AuditLogService();

        $party = $partyModel->findByMobile($mobile);
        $wasNew = false;

        if (!$party) {
            $party = $partyModel->createParty($mobile);
            $partyModel->update($party['id'], ['mobile_verified_at' => date('Y-m-d H:i:s')]);
            $wasNew = true;
            CLI::write("Created new party {$party['id']} for {$mobile}.", 'green');
        } else {
            CLI::write("Party {$party['id']} already registered for {$mobile} — resetting to bootstrap state.", 'yellow');
        }

        $partyModel->update($party['id'], ['recovery_email' => $email]);
        $credentialModel->setCredential($party['id'], $email, password_hash($password, PASSWORD_BCRYPT));

        if (!$roleModel->hasActiveRole($party['id'], 'super_admin', null)) {
            $roleModel->grantRole($party['id'], 'super_admin', null);
            CLI::write('Granted super_admin role.', 'green');
        } else {
            CLI::write('super_admin role already held.', 'yellow');
        }

        $audit->log('admin.custodian_bootstrapped', $party['id'], [
            'email' => $email, 'mobile' => $mobile, 'wasNewParty' => $wasNew,
        ]);

        CLI::write("✓ Custodian account ready: {$email}.", 'green');
        CLI::write('', 'white');
        CLI::write('One real step still required (cannot be scripted — needs a physical', 'yellow');
        CLI::write('authenticator app): log in at /admin/login with this email + password, then', 'yellow');
        CLI::write('visit /admin/setup-totp to scan the QR code and enable 2FA before', 'yellow');
        CLI::write('/admin/login will fully work — BR-04 requires TOTP on every Custodian login.', 'yellow');
    }
}
