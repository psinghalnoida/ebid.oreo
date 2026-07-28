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

    // BR-04: the real separate Super Admin login — mobile + mPIN (same
    // credential mechanism as regular users, per the existing schema) +
    // a genuinely-verified TOTP code, all three required.
    public function login(string $mobileNumber, string $mpin, string $totpCode): array
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
}
