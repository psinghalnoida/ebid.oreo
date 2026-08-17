<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\SuperAdminBackupCodeModel;

class SuperAdminAuthService
{
    private PartyModel $partyModel;
    private AuthorizationService $authz;
    private SuperAdminBackupCodeModel $backupCodeModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->authz = new AuthorizationService();
        $this->backupCodeModel = new SuperAdminBackupCodeModel();
    }

    // Only a party already granted the super_admin role (via
    // `php spark grant:super-admin`) can set up TOTP — this doesn't grant
    // the role itself, only enrolls a second factor for someone who
    // already has it.
    // PR-17: Super Admin credential recovery. First-time setup (no
    // confirmed secret exists yet) only needs the regular login +
    // super_admin role — matching D-29's original bootstrap flow, since
    // a genuinely new Super Admin has no old device to confirm with.
    // RE-enrollment (a confirmed secret already exists) is the real gap
    // this fixes: it now requires the caller to have ALREADY passed
    // through the isolated /admin/login TOTP-gated session — the same
    // `super_admin_totp_verified_at` marker SuperAdminFilter checks —
    // not just being logged in via the standard mobile+mPIN channel.
    // Before this fix, anyone with only the regular session could
    // silently overwrite an existing Super Admin's TOTP secret with
    // their own, with zero confirmation of the old one — exactly the
    // credential-hijack PR-17/BR-20 exist to prevent.
    public function beginTotpSetup(string $partyId, bool $isIsolatedSessionVerified = false): array
    {
        if (!$this->authz->isSuperAdmin($partyId)) {
            throw new \RuntimeException('Only a party already granted the super_admin role may set up 2FA.');
        }
        $party = $this->partyModel->find($partyId);
        $isReEnrollment = !empty($party['totp_secret']) && !empty($party['totp_enabled_at']);
        if ($isReEnrollment && !$isIsolatedSessionVerified) {
            throw new \RuntimeException(
                'PR-17: re-enrolling a new authenticator requires confirming your CURRENT one first — log in via /admin/login with your existing device, then return here. If that device is genuinely lost, this needs the CLI reset-totp path instead (server access required).'
            );
        }

        $secret = TotpService::generateSecret();
        $this->partyModel->update($partyId, ['totp_secret' => $secret]);
        $uri = TotpService::getProvisioningUri($secret, $party['mobile_number']);
        return ['secret' => $secret, 'provisioningUri' => $uri, 'isReEnrollment' => $isReEnrollment];
    }

    // Returns the plain-text backup codes on success (shown exactly
    // once — only the hash is ever stored) or null if the code was
    // wrong. Regenerating on every confirm (first enrollment or
    // re-enrollment) deliberately invalidates any prior codes, which
    // were trust-bound to the old device/enrollment context.
    public function confirmTotpSetup(string $partyId, string $code): ?array
    {
        $party = $this->partyModel->find($partyId);
        if (!$party['totp_secret']) {
            throw new \RuntimeException('No TOTP secret has been generated yet — call beginTotpSetup first.');
        }
        if (!TotpService::verifyCode($party['totp_secret'], $code)) {
            return null;
        }
        $wasReEnrollment = !empty($party['totp_enabled_at']);
        $this->partyModel->update($partyId, ['totp_enabled_at' => date('Y-m-d H:i:s')]);
        $backupCodes = $this->backupCodeModel->regenerateFor($partyId);

        // PR-17's own explicit final step: "logs the credential change
        // in the immutable audit registry."
        (new \App\Libraries\AuditLogService())->log(
            $wasReEnrollment ? 'admin.totp_reenrolled' : 'admin.totp_first_enrolled',
            $partyId,
            ['wasReEnrollment' => $wasReEnrollment, 'backupCodesRegenerated' => count($backupCodes)]
        );

        return $backupCodes;
    }

    // D-128: TEMPORARY toggle, at the project owner's explicit request —
    // "remove TOTP till we test it properly, use email instead." Reads
    // admin.twoFactorMode from .env; defaults to 'totp' (the real BR-04
    // requirement) if that key is absent, so a fresh deploy that never
    // touches this setting stays on the secure-by-default path. Only an
    // explicit `admin.twoFactorMode = email_otp` in a specific server's
    // .env switches it — this file's own code never assumes which mode
    // is active. Meant to be reverted (delete the .env line, or set it
    // back to 'totp') once TOTP has been tested properly; not a
    // permanent replacement for BR-04's second factor.
    public static function twoFactorMode(): string
    {
        $mode = env('admin.twoFactorMode', 'totp');
        return $mode === 'email_otp' ? 'email_otp' : 'totp';
    }

    // Shared by both the TOTP path (login(), below) and the email-OTP
    // path (requestLoginEmailOtp()/completeLoginWithEmailOtp()) — mobile
    // + mPIN + super_admin role is the common first stage regardless of
    // which second factor is active.
    private function verifyMobileAndMpin(string $mobileNumber, string $mpin): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$party['mpin_hash']) {
            throw new \RuntimeException('No registered account with a set mPIN for this mobile number.');
        }
        if (!password_verify($mpin, $party['mpin_hash'])) {
            throw new \RuntimeException('Incorrect mPIN.');
        }
        if (!$this->authz->isSuperAdmin($party['id'])) {
            throw new \RuntimeException('This account does not have Super Admin access.');
        }
        return $party;
    }

    // BR-04: the real separate Super Admin login — mobile + mPIN (same
    // credential mechanism as regular users, per the existing schema) +
    // a genuinely-verified TOTP code, all three required. Only used when
    // twoFactorMode() === 'totp' (the default) — the controller branches
    // to requestLoginEmailOtp()/completeLoginWithEmailOtp() instead when
    // the D-128 toggle is set to 'email_otp'.
    public function login(string $mobileNumber, string $mpin, string $totpCode): array
    {
        $party = $this->verifyMobileAndMpin($mobileNumber, $mpin);
        if (!$party['totp_enabled_at'] || !$party['totp_secret']) {
            throw new \RuntimeException('TOTP has not been set up for this account yet.');
        }
        if (!TotpService::verifyCode($party['totp_secret'], $totpCode)) {
            // PR-17 fallback: a valid, unused backup code stands in for
            // the authenticator app when the device is unavailable.
            if (!$this->backupCodeModel->consumeIfValid($party['id'], $totpCode)) {
                throw new \RuntimeException('Invalid or expired authentication code.');
            }
            (new \App\Libraries\AuditLogService())->log('admin.totp_backup_code_used', $party['id'], []);
        }
        return $party;
    }

    // D-128: stage 1 of the email-OTP login path — validates mobile +
    // mPIN + super_admin role (same as login()'s first stage), then
    // sends a real OTP to the account's recovery_email. Throws if no
    // recovery_email is on file, rather than silently failing to send
    // anything — bootstrap:custodian sets one by default, but an older
    // or differently-provisioned account might not have one.
    public function requestLoginEmailOtp(string $mobileNumber, string $mpin): array
    {
        $party = $this->verifyMobileAndMpin($mobileNumber, $mpin);
        if (empty($party['recovery_email'])) {
            throw new \RuntimeException('No recovery email is on file for this account — email-based login cannot proceed. Set one (e.g. via bootstrap:custodian) or switch admin.twoFactorMode back to totp.');
        }
        $auth = new AuthService();
        $otp = $auth->requestEmailOtp($party['recovery_email'], 'admin_login_email');
        $emailSent = (new EmailNotificationService())->sendOtp($party['recovery_email'], $otp, 'admin_login_email');

        return ['party' => $party, 'otp' => $otp, 'emailSent' => $emailSent];
    }

    // D-128: stage 2 — verifies the emailed code and completes login.
    // Deliberately re-checks isSuperAdmin() rather than trusting the
    // party id alone came from a legitimate stage-1 call — the same
    // defensive posture login()'s TOTP path already has via
    // verifyMobileAndMpin().
    public function completeLoginWithEmailOtp(string $partyId, string $submittedOtp): array
    {
        $party = $this->partyModel->find($partyId);
        if (!$party || !$this->authz->isSuperAdmin($partyId) || empty($party['recovery_email'])) {
            throw new \RuntimeException('Invalid session — start the login again.');
        }
        $auth = new AuthService();
        if (!$auth->verifyEmailOtp($party['recovery_email'], $submittedOtp, 'admin_login_email')) {
            throw new \RuntimeException('Incorrect or expired code.');
        }
        return $party;
    }
}
