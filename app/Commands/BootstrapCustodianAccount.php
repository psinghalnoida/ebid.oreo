<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\PartyRoleModel;
use App\Libraries\AuthService;
use App\Libraries\AuditLogService;

// Project owner's explicit request: a known, working Custodian (Super
// Admin) account for the live system, seeded with a specific mobile
// number and mPIN rather than going through the normal self-registration
// flow. Deliberately implemented as a real, bcrypt-hashed database
// record created by a controlled server-side command — NOT as a literal
// credential comparison inside the login path itself. The latter would
// be a genuine backdoor (a bypass baked into the auth logic that no
// audit of app/Libraries/SuperAdminAuthService.php would ever remove);
// this instead produces a completely ordinary party row that goes
// through the exact same mPIN/TOTP verification as any other Custodian
// account. `password_verify()` in SuperAdminAuthService::login() has no
// idea this party was created by a CLI command instead of the
// self-registration form.
//
// The mobile number, mPIN, and recovery email default to exactly what
// the project owner specified, so `php spark bootstrap:custodian` with
// no arguments reproduces that account. All three are overridable
// arguments precisely so this literal mPIN doesn't have to be the one
// actually in use forever — change it via `/admin/login` once,
// ordinary session, then re-run this command with different arguments
// (or just use the normal mPIN-change path once one exists) if a
// different value is wanted later. Re-running is always safe: this
// updates the mPIN/recovery email on an existing account rather than
// erroring, so it doubles as a "reset this account back to its known
// bootstrap state" command.
class BootstrapCustodianAccount extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'bootstrap:custodian';
    protected $description = 'Creates or resets the project owner\'s known Custodian (Super Admin) account.';
    protected $usage        = 'bootstrap:custodian [mobile_number] [mpin] [recovery_email]';

    public function run(array $params)
    {
        $mobile = $params[0] ?? '+919811047785';
        $mpin = $params[1] ?? '4148';
        $recoveryEmail = $params[2] ?? 'psinghalnoida@gmail.com';

        if (!AuthService::isValidIndianMobile($mobile)) {
            CLI::error("Invalid mobile number format: {$mobile} (expected +91XXXXXXXXXX)");
            return;
        }
        if (!preg_match('/^\d{4}$/', $mpin)) {
            CLI::error('mPIN must be exactly 4 digits.');
            return;
        }
        if (!filter_var($recoveryEmail, FILTER_VALIDATE_EMAIL)) {
            CLI::error("Invalid recovery email: {$recoveryEmail}");
            return;
        }

        $partyModel = new PartyModel();
        $roleModel = new PartyRoleModel();
        $auth = new AuthService();
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

        $auth->setMpin($party['id'], $mpin);
        $partyModel->update($party['id'], ['recovery_email' => $recoveryEmail]);

        if (!$roleModel->hasActiveRole($party['id'], 'super_admin', null)) {
            $roleModel->grantRole($party['id'], 'super_admin', null);
            CLI::write('Granted super_admin role.', 'green');
        } else {
            CLI::write('super_admin role already held.', 'yellow');
        }

        $audit->log('admin.custodian_bootstrapped', $party['id'], [
            'mobile' => $mobile, 'recoveryEmail' => $recoveryEmail, 'wasNewParty' => $wasNew,
        ]);

        CLI::write("✓ Custodian account ready: {$mobile}, recovery email {$recoveryEmail}.", 'green');
        CLI::write('', 'white');
        CLI::write('One real step still required (cannot be scripted — needs a physical', 'yellow');
        CLI::write('authenticator app): log in at /login with this mobile + mPIN, then', 'yellow');
        CLI::write('visit /admin/setup-totp to scan the QR code and enable 2FA before', 'yellow');
        CLI::write('/admin/login will work — BR-04 requires TOTP on every Custodian login.', 'yellow');
    }
}
