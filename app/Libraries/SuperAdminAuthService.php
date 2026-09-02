<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\SuperAdminBackupCodeModel;
use App\Models\SuperAdminCredentialModel;

class SuperAdminAuthService
{
    // Same figure BR-02's regular mPIN lockout uses (AuthService) —
    // kept independent here since it locks super_admin_credential's own
    // failed_mpin_attempts counter, not party's.
    private const MPIN_FAILURE_LOCKOUT_THRESHOLD = 3;

    private PartyModel $partyModel;
    private AuthorizationService $authz;
    private SuperAdminBackupCodeModel $backupCodeModel;
    private SuperAdminCredentialModel $credentialModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->authz = new AuthorizationService();
        $this->backupCodeModel = new SuperAdminBackupCodeModel();
        $this->credentialModel = new SuperAdminCredentialModel();
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
    // path (requestLoginEmailOtp()/completeLoginWithEmailOtp()) — email
    // + password + super_admin role is the common first stage regardless
    // of which second factor is active. Custodian login uses its own
    // super_admin_credential table (email + bcrypt password), separate
    // from the shared party.mpin_hash used by every other role — see
    // CreateSuperAdminCredential migration.
    private function verifyEmailAndPassword(string $email, string $password): array
    {
        $credential = $this->credentialModel->findByEmail($email);
        if (!$credential || !password_verify($password, $credential['password_hash'])) {
            throw new \RuntimeException('Incorrect email or password.');
        }
        $party = $this->partyModel->findActiveById($credential['party_id']);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            throw new \RuntimeException('This account does not have Super Admin access.');
        }
        return $party;
    }

    // BR-04: the real separate Super Admin login — email + password +
    // a genuinely-verified TOTP code, all three required. Only used when
    // twoFactorMode() === 'totp' (the default) — the controller branches
    // to requestLoginEmailOtp()/completeLoginWithEmailOtp() instead when
    // the D-128 toggle is set to 'email_otp'.
    public function login(string $email, string $password, string $totpCode): array
    {
        $party = $this->verifyEmailAndPassword($email, $password);
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

    // D-128: stage 1 of the email-OTP login path — validates email +
    // password + super_admin role (same as login()'s first stage), then
    // sends a real OTP to the account's recovery_email. Throws if no
    // recovery_email is on file, rather than silently failing to send
    // anything — bootstrap:custodian sets one by default, but an older
    // or differently-provisioned account might not have one.
    public function requestLoginEmailOtp(string $email, string $password): array
    {
        $party = $this->verifyEmailAndPassword($email, $password);
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

    // ── Mobile + mPIN login (default Custodian login method) ─────────
    //
    // Independent of the legacy email+password path above: a Custodian's
    // admin mPIN lives on super_admin_credential.mpin_hash (see
    // AddMpinToSuperAdminCredential), not party.mpin_hash — that column
    // stays reserved for a Custodian's OTHER roles, if any. Mirrors
    // AuthService::authenticateWithMpin()'s three-outcome shape so
    // SuperAdminAuthApiController::loginMpin() can reuse the same
    // response contract regular users' loginWithMpin() has.

    public function loginMethods(string $mobileNumber): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            return ['mpin_enabled' => false, 'totp_enabled' => false];
        }
        $credential = $this->credentialModel->findByPartyId($party['id']);
        return [
            'mpin_enabled' => !empty($credential['mpin_hash'] ?? null),
            'totp_enabled' => !empty($party['totp_enabled_at']),
        ];
    }

    // Sets/resets the Custodian mPIN. Requires a super_admin_credential
    // row to already exist (created by bootstrap:custodian, or whatever
    // grants the super_admin role) — an admin mPIN is layered onto that
    // record, not a replacement for it.
    public function setMpin(string $partyId, string $mpin): void
    {
        if (!preg_match('/^\d{4}$/', $mpin)) {
            throw new \RuntimeException('mPIN must be exactly 4 digits.');
        }
        $credential = $this->credentialModel->findByPartyId($partyId);
        if (!$credential) {
            throw new \RuntimeException('No Custodian credential record exists for this account yet.');
        }
        $this->credentialModel->setMpinHash($credential['id'], password_hash($mpin, PASSWORD_BCRYPT));
    }

    // Same three outcomes as AuthService::authenticateWithMpin():
    //  - ['status' => 'ok', 'party' => ...]
    //  - ['status' => 'otp_required', 'partyId' => ...]  (3-strike lockout)
    //  - ['status' => 'invalid_mpin', 'attemptsRemaining' => n]
    public function authenticateWithMpin(string $mobileNumber, string $mpin): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            throw new \RuntimeException('Incorrect mobile number or mPIN.');
        }
        $credential = $this->credentialModel->findByPartyId($party['id']);
        if (!$credential || empty($credential['mpin_hash'])) {
            throw new \RuntimeException('mPIN login has not been set up for this account yet — use "Forgot mPIN?" to set one.');
        }

        if (password_verify($mpin, $credential['mpin_hash'])) {
            $this->credentialModel->resetFailedMpinAttempts($credential['id']);
            return ['status' => 'ok', 'party' => $party];
        }

        $attempts = $this->credentialModel->incrementFailedMpinAttempts($credential['id']);
        if ($attempts >= self::MPIN_FAILURE_LOCKOUT_THRESHOLD) {
            return ['status' => 'otp_required', 'partyId' => $party['id']];
        }
        return ['status' => 'invalid_mpin', 'attemptsRemaining' => self::MPIN_FAILURE_LOCKOUT_THRESHOLD - $attempts];
    }

    // ── Google Authenticator login (equal alternative to mPIN) ───────
    //
    // A verified TOTP code alone is sufficient — no password, no mPIN —
    // matching the login page's "either one" requirement. Falls back to
    // an unused backup code exactly like the legacy login()'s TOTP check.
    public function loginWithTotpCode(string $mobileNumber, string $totpCode): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            throw new \RuntimeException('Incorrect mobile number or code.');
        }
        if (empty($party['totp_enabled_at']) || empty($party['totp_secret'])) {
            throw new \RuntimeException('Google Authenticator is not enabled for this account.');
        }
        if (!TotpService::verifyCode($party['totp_secret'], $totpCode)) {
            if (!$this->backupCodeModel->consumeIfValid($party['id'], $totpCode)) {
                throw new \RuntimeException('Invalid or expired authenticator code.');
            }
            (new \App\Libraries\AuditLogService())->log('admin.totp_backup_code_used', $party['id'], []);
        }
        return $party;
    }

    // ── Forgot mPIN — mobile (+ email, if on file) OTP recovery ───────
    //
    // Mirrors UserAuthApiService::requestForgotPassword()'s dual-channel
    // pattern exactly, but produces admin-scoped ticket types and resets
    // super_admin_credential.mpin_hash instead of party.mpin_hash.
    public function requestMpinReset(string $mobileNumber): array
    {
        $genericMessage = 'If that number belongs to a registered Custodian account, a reset code has just been sent to it (and to the recovery email on file, if one is set).';

        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            return ['message' => $genericMessage];
        }

        $accountAuth = new AuthService();
        $otp = $accountAuth->requestOtp($mobileNumber, 'mpin_reset');
        $ticketClaims = ['sub' => $party['id'], 'mobile' => $mobileNumber];

        $response = ['message' => $genericMessage, 'dev_otp' => $otp];
        if (!empty($party['recovery_email'])) {
            $ticketClaims['email'] = $party['recovery_email'];
            $emailOtp = $accountAuth->requestEmailOtp($party['recovery_email'], 'mpin_reset_email');
            $response['dev_email_otp'] = $emailOtp;
            $response['email_sent'] = (new EmailNotificationService())->sendOtp($party['recovery_email'], $emailOtp, 'mpin_reset_email');
            $response['email'] = $party['recovery_email'];
        }

        $response['pending_ticket'] = UserAuthApiService::issuePendingTicket('admin_mpin_reset_otp_pending', $ticketClaims);

        (new \App\Libraries\AuditLogService())->log('admin.mpin_reset_requested', $party['id'], [
            'emailDeliveredForReal' => $response['email_sent'] ?? null,
        ]);

        return $response;
    }

    public function verifyMpinResetOtp(string $pendingTicket, string $otp, ?string $emailOtp): string
    {
        $claims = UserAuthApiService::decodePendingTicket($pendingTicket, 'admin_mpin_reset_otp_pending');
        if (!$claims) {
            throw new \RuntimeException('Invalid or expired pending_ticket. Start the reset again.');
        }

        $accountAuth = new AuthService();
        if (!$accountAuth->verifyOtp($claims['mobile'], 'mpin_reset', $otp)) {
            throw new \RuntimeException('Incorrect or expired mobile OTP.');
        }
        if (!empty($claims['email'])) {
            if (!$accountAuth->verifyEmailOtp($claims['email'], (string) $emailOtp, 'mpin_reset_email')) {
                throw new \RuntimeException('Mobile OTP correct, but the email OTP was incorrect or expired. Both are required together.');
            }
        }

        return UserAuthApiService::issuePendingTicket('admin_mpin_setup_pending', ['sub' => $claims['sub']]);
    }

    public function completeMpinReset(string $pendingTicket, string $mpin): array
    {
        $claims = UserAuthApiService::decodePendingTicket($pendingTicket, 'admin_mpin_setup_pending');
        if (!$claims) {
            throw new \RuntimeException('Invalid or expired pending_ticket. Start the reset again.');
        }

        $this->setMpin($claims['sub'], $mpin);
        $party = $this->partyModel->find($claims['sub']);
        (new \App\Libraries\AuditLogService())->log('admin.mpin_reset', $party['id'], []);

        return $party;
    }

    // Forgot-password: sets a brand new bcrypt password hash on the
    // Custodian's super_admin_credential row. Only ever called after the
    // caller has already proven ownership of the account's recovery
    // email via a verified OTP (SuperAdminAuthApiController's
    // forgot-password flow) — this method itself does no verification.
    public function resetPassword(string $partyId, string $newPassword): void
    {
        $this->credentialModel->setPasswordHash($partyId, password_hash($newPassword, PASSWORD_BCRYPT));
        (new \App\Libraries\AuditLogService())->log('admin.password_reset', $partyId, []);
    }
}
