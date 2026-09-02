<?php

namespace App\Controllers;

use App\Libraries\SuperAdminAuthService;
use App\Libraries\AuthService;
use App\Libraries\AuthorizationService;
use App\Libraries\EmailNotificationService;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Libraries\AuditLogService;
use App\Models\PartyModel;
use App\Models\SuperAdminCredentialModel;

// JWT counterpart of the former SuperAdminAuthController (BR-04's
// separate, TOTP/email-OTP-verified Super Admin login, plus 2FA
// enrollment and dual-channel forgot-mPIN recovery). Reuses
// SuperAdminAuthService/AuthService for every actual auth decision —
// this controller only reshapes it as JSON + JWT.
class SuperAdminAuthApiController extends BaseController
{
    private SuperAdminAuthService $auth;
    private AuthService $accountAuth;
    private UserAuthApiService $tokens;

    public function __construct()
    {
        $this->auth = new SuperAdminAuthService();
        $this->accountAuth = new AuthService();
        $this->tokens = new UserAuthApiService();
    }

    // ── 2FA enrollment (jwtAuth — any logged-in party who already
    // holds the super_admin DB role; enrolling 2FA doesn't grant the
    // role itself, that's grant:super-admin) ────────────────────────

    // POST /api/v1/admin/auth/setup-totp
    public function setupTotp()
    {
        $partyId = UserAuthContext::partyId();
        // PR-17: was the caller genuinely authenticated via THIS token
        // having come from the isolated Super Admin login (proving they
        // hold the CURRENT device), not just a regular party token?
        $isolatedVerified = UserAuthContext::hasRole('super_admin');

        try {
            $setup = $this->auth->beginTotpSetup($partyId, $isolatedVerified);
        } catch (\RuntimeException $e) {
            return $this->jsonError(403, 'setup_failed', $e->getMessage());
        }

        return $this->apiResponse(['setup' => $this->withQrCode($setup)]);
    }

    // POST /api/v1/admin/auth/setup-totp/confirm  { code }
    public function confirmSetupTotp()
    {
        $partyId = UserAuthContext::partyId();
        $code = $this->input('code');

        try {
            $confirmed = $this->auth->confirmTotpSetup($partyId, $code);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'confirm_failed', $e->getMessage());
        }
        if (!$confirmed) {
            return $this->jsonError(401, 'invalid_code', 'Invalid code — check your authenticator app and try again.');
        }

        // Shown exactly once — the plain codes are never persisted,
        // only their bcrypt hashes (in super_admin_backup_code).
        return $this->apiResponse(['backupCodes' => $confirmed]);
    }

    // ── 2FA enrollment, mobile-OTP entry point ────────────────────────
    //
    // The jwtAuth pair above assumes the caller already has SOME access
    // token, which for a Custodian created via bootstrap:custodian (email
    // + password only, no mPIN) meant first minting one through the
    // unrelated mPIN-registration flow just to reach setup-totp — a
    // roundabout, technical prerequisite for what should be a "prove you
    // own this phone, then scan a QR" flow. These three endpoints do that
    // directly: mobile + OTP is the proof, and a narrowly-scoped
    // 'admin_totp_setup_pending' ticket (NOT a full access token —
    // usable for nothing but confirming this specific enrollment) carries
    // the party from OTP verification to confirmation.

    // POST /api/v1/admin/auth/setup-totp/request-otp  { mobile_number }
    public function setupTotpRequestOtp()
    {
        $mobile = trim((string) $this->input('mobile_number'));

        try {
            $otp = $this->accountAuth->requestOtp($mobile, 'registration');
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'invalid_mobile', $e->getMessage());
        }

        return $this->apiResponse(['dev_otp' => $otp]);
    }

    // POST /api/v1/admin/auth/setup-totp/verify-otp  { mobile_number, otp }
    public function setupTotpVerifyOtp()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $otp = trim((string) $this->input('otp'));

        if (!$this->accountAuth->verifyOtp($mobile, 'registration', $otp)) {
            return $this->jsonError(401, 'invalid_otp', 'Incorrect or expired code.');
        }

        $party = (new PartyModel())->findByMobile($mobile);
        if (!$party || !(new AuthorizationService())->isSuperAdmin($party['id'])) {
            return $this->jsonError(403, 'not_super_admin', 'This mobile number is not registered to a Super Admin account.');
        }

        // Same first-time-only rule as setupTotp() above (isolatedVerified
        // = false) — re-enrolling over an already-confirmed secret still
        // requires PR-17's isolated TOTP-verified session, not just phone
        // ownership, and beginTotpSetup() enforces that on its own.
        try {
            $setup = $this->auth->beginTotpSetup($party['id'], false);
        } catch (\RuntimeException $e) {
            return $this->jsonError(403, 'setup_failed', $e->getMessage());
        }

        $pendingTicket = UserAuthApiService::issuePendingTicket('admin_totp_setup_pending', ['sub' => $party['id']]);

        return $this->apiResponse([
            'pending_ticket' => $pendingTicket,
            'setup' => $this->withQrCode($setup),
        ]);
    }

    // POST /api/v1/admin/auth/setup-totp/confirm-mobile  { pending_ticket, code }
    public function confirmSetupTotpByMobile()
    {
        $claims = UserAuthApiService::decodePendingTicket(
            (string) $this->input('pending_ticket'),
            'admin_totp_setup_pending'
        );
        if (!$claims) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket. Start setup again.');
        }

        try {
            $confirmed = $this->auth->confirmTotpSetup($claims['sub'], (string) $this->input('code'));
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'confirm_failed', $e->getMessage());
        }
        if (!$confirmed) {
            return $this->jsonError(401, 'invalid_code', 'Invalid code — check your authenticator app and try again.');
        }

        return $this->apiResponse(['backupCodes' => $confirmed]);
    }

    // Renders the provisioning URI as a scannable QR code, server side,
    // rather than leaving callers to find their own way to turn an
    // otpauth:// URI into an image — same endroid/qr-code usage as
    // ChronicleController's verification QR. Purely a display
    // convenience: the secret/provisioningUri stay in the response as-is
    // for manual entry or a caller that wants to render its own.
    private function withQrCode(array $setup): array
    {
        $qrResult = (new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $setup['provisioningUri'], size: 240, margin: 8,
        ))->build();
        $setup['qrCodeDataUri'] = $qrResult->getDataUri();
        return $setup;
    }

    // ── TEMPORARY testing bypass ──────────────────────────────────────
    //
    // Project owner's explicit request: the Custodian login method is
    // being replaced and nobody can currently get into the admin area to
    // test it. Mirrors JwtSuperAdminFilter's admin.authBypass gate — see
    // that class's docblock for the full rationale. Off (404) unless
    // admin.authBypass=true is explicitly set in .env; issues a real
    // access token for the first super_admin party, no credentials of
    // any kind checked. DELETE THIS METHOD (and the matching block in
    // JwtSuperAdminFilter) once the real Custodian login is built.

    // POST /api/v1/app/admin/auth/dev-bypass-login
    public function devBypassLogin()
    {
        if (env('admin.authBypass', false) !== true && env('admin.authBypass') !== 'true') {
            return $this->jsonError(404, 'not_found', 'Not found.');
        }

        $party = (new AuthorizationService())->firstSuperAdminParty();
        if (!$party) {
            return $this->jsonError(500, 'no_super_admin', 'admin.authBypass is on but no party holds the super_admin role yet — run php spark bootstrap:custodian first.');
        }

        $audit = new AuditLogService();
        $audit->log('admin.login.success', $party['id'], ['method' => 'DEV_BYPASS — no credentials checked'], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        return $this->completeLogin($party, $audit, $this->request->getIPAddress(), (string) $this->request->getUserAgent(), false);
    }

    // ── Login (default: mobile + mPIN, alternative: Google Authenticator) ──
    //
    // The Custodian login redesign's real login path. Both methods are
    // equal, standalone single factors — see SuperAdminAuthService's
    // docblocks on authenticateWithMpin()/loginWithTotpCode() for why.
    // The legacy email+password+2FA flow further below stays in place,
    // unused by the login UI, as a fallback.

    // POST /api/v1/app/admin/auth/login-methods  { mobile_number }
    // Tells the login page which method(s) to offer for this mobile
    // number — by default only mobile+mPIN is shown; Google Authenticator
    // only appears once it's been enabled. Always returns a shape (never
    // a 404/error) so this can't be used to probe which numbers exist
    // any more precisely than the login attempts themselves already do.
    public function loginMethods()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        return $this->apiResponse($this->auth->loginMethods($mobile));
    }

    // POST /api/v1/app/admin/auth/login-mpin  { mobile_number, mpin }
    // Three shapes back, mirroring SuperAdminAuthService::authenticateWithMpin():
    // {status:"ok", access_token, ...}, {status:"otp_required",
    // pending_ticket, ...}, or {status:"invalid_mpin", attemptsRemaining}.
    public function loginMpin()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $mpin = trim((string) $this->input('mpin'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        try {
            $result = $this->auth->authenticateWithMpin($mobile, $mpin);
        } catch (\RuntimeException $e) {
            $audit->log('admin.login.failed', null, ['mobile' => $mobile, 'reason' => $e->getMessage()], $ip, $userAgent);
            return $this->jsonError(401, 'login_failed', $e->getMessage());
        }

        if ($result['status'] === 'ok') {
            $audit->log('admin.login.success', $result['party']['id'], ['method' => 'mpin'], $ip, $userAgent);
            return $this->completeLogin($result['party'], $audit, $ip, $userAgent, false);
        }

        if ($result['status'] === 'otp_required') {
            $audit->log('admin.login.otp_required', $result['partyId'], ['mobile' => $mobile], $ip, $userAgent);
            $reset = $this->auth->requestMpinReset($mobile);
            return $this->apiResponse(['status' => 'otp_required'] + $reset);
        }

        // 'invalid_mpin'
        $audit->log('admin.login.invalid_mpin', null, ['mobile' => $mobile], $ip, $userAgent);
        return $this->jsonError(401, 'invalid_mpin', "Incorrect mPIN. {$result['attemptsRemaining']} attempt(s) remaining before OTP verification is required.");
    }

    // POST /api/v1/app/admin/auth/login-totp  { mobile_number, totp_code }
    public function loginTotp()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $totpCode = trim((string) $this->input('totp_code'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        try {
            $party = $this->auth->loginWithTotpCode($mobile, $totpCode);
        } catch (\RuntimeException $e) {
            $audit->log('admin.login.failed', null, ['mobile' => $mobile, 'method' => 'totp', 'reason' => $e->getMessage()], $ip, $userAgent);
            return $this->jsonError(401, 'login_failed', $e->getMessage());
        }

        $audit->log('admin.login.success', $party['id'], ['method' => 'totp'], $ip, $userAgent);
        return $this->completeLogin($party, $audit, $ip, $userAgent, false);
    }

    // ── Forgot mPIN — mobile (+ email, if on file) OTP recovery ──────

    // POST /api/v1/app/admin/auth/mpin/forgot  { mobile_number }
    public function mpinForgotRequest()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        if (!AuthService::isValidIndianMobile($mobile)) {
            return $this->jsonError(422, 'invalid_mobile_number', 'Expected a 10-digit Indian mobile number in +91XXXXXXXXXX format.');
        }
        return $this->apiResponse($this->auth->requestMpinReset($mobile));
    }

    // POST /api/v1/app/admin/auth/mpin/forgot/verify  { pending_ticket, otp, email_otp? }
    // -> pending_ticket for mpinForgotComplete(), below
    public function mpinForgotVerify()
    {
        $ticket = (string) $this->input('pending_ticket');
        $otp = trim((string) $this->input('otp'));
        $emailOtp = $this->input('email_otp') !== null ? trim((string) $this->input('email_otp')) : null;

        try {
            $setupTicket = $this->auth->verifyMpinResetOtp($ticket, $otp, $emailOtp);
        } catch (\RuntimeException $e) {
            return $this->jsonError(401, 'invalid_otp', $e->getMessage());
        }
        return $this->apiResponse(['pending_ticket' => $setupTicket]);
    }

    // POST /api/v1/app/admin/auth/mpin/forgot/complete  { pending_ticket, mpin }
    public function mpinForgotComplete()
    {
        $ticket = (string) $this->input('pending_ticket');
        $mpin = trim((string) $this->input('mpin'));

        try {
            $party = $this->auth->completeMpinReset($ticket, $mpin);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'mpin_setup_failed', $e->getMessage());
        }

        (new AuditLogService())->log('admin.login.success', $party['id'], ['method' => 'mpin_reset'], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        return $this->completeLogin($party, new AuditLogService(), $this->request->getIPAddress(), (string) $this->request->getUserAgent(), false);
    }

    // ── Legacy login (BR-04, email + password + TOTP/email-OTP) ──────
    //
    // Kept in place as an unused fallback per the login redesign — the
    // login page no longer offers this path, but the backend and
    // super_admin_credential.password_hash stay intact.

    // POST /api/v1/admin/auth/login  { email, password, totp_code? }
    public function login()
    {
        $email = trim((string) $this->input('email'));
        $password = (string) $this->input('password');
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        if (SuperAdminAuthService::twoFactorMode() === 'email_otp') {
            try {
                $result = $this->auth->requestLoginEmailOtp($email, $password);
            } catch (\RuntimeException $e) {
                return $this->jsonError(401, 'login_failed', $e->getMessage());
            }

            $audit->log('admin.login_email_otp_requested', $result['party']['id'], ['emailDeliveredForReal' => $result['emailSent']], $ip, $userAgent);

            $pendingTicket = UserAuthApiService::issuePendingTicket('admin_login_email_pending', ['sub' => $result['party']['id']]);
            return $this->apiResponse([
                'stage' => 'email_otp_required',
                'pending_ticket' => $pendingTicket,
                'email' => $result['party']['recovery_email'],
                'email_sent' => $result['emailSent'],
                'dev_otp' => $result['otp'],
            ]);
        }

        $totpCode = trim((string) $this->input('totp_code'));
        try {
            $party = $this->auth->login($email, $password, $totpCode);
        } catch (\RuntimeException $e) {
            return $this->jsonError(401, 'login_failed', $e->getMessage());
        }

        return $this->completeLogin($party, $audit, $ip, $userAgent);
    }

    // POST /api/v1/admin/auth/login/verify-email  { pending_ticket, otp }
    public function verifyEmail()
    {
        $ticket = (string) $this->input('pending_ticket');
        $otp = trim((string) $this->input('otp'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        $claims = UserAuthApiService::decodePendingTicket($ticket, 'admin_login_email_pending');
        if (!$claims) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket. Start the login again.');
        }

        try {
            $party = $this->auth->completeLoginWithEmailOtp($claims['sub'], $otp);
        } catch (\RuntimeException $e) {
            return $this->jsonError(401, 'invalid_otp', $e->getMessage());
        }

        return $this->completeLogin($party, $audit, $ip, $userAgent);
    }

    // $logAudit = false when the caller already logged its own, more
    // specific 'admin.login.success' event (e.g. with a 'method' tag) —
    // avoids a duplicate generic entry right next to it.
    private function completeLogin(array $party, AuditLogService $audit, string $ip, string $userAgent, bool $logAudit = true)
    {
        if ($logAudit) {
            $audit->log('admin.login.success', $party['id'], [], $ip, $userAgent);
        }
        $accessToken = $this->tokens->issueAccessToken($party, ['party', 'super_admin']);

        return $this->apiResponse([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 86400,
            'party' => [
                'id' => $party['id'],
                'mobile_number' => $party['mobile_number'],
                'full_name' => $party['full_name'] ?? null,
                'email' => $party['recovery_email'] ?? null,
            ],
        ]);
    }

    // ── Forgot password — email-only recovery, unauthenticated ────
    //
    // Now that Custodian login is email + password (super_admin_credential
    // table, not mPIN), recovery keys off the same login email rather
    // than a mobile number. Always returns the same generic response
    // whether or not the email belongs to a real Custodian account —
    // this endpoint is reachable unauthenticated and must not become an
    // oracle for which emails hold the super_admin role.
    //
    // Sent via EmailNotificationService (CI4's Email service — see
    // app/Config/Email.php + env's `email.*` settings). Reuses the
    // existing 'mpin_reset_email' otp_verification purpose (a generic
    // "OTP emailed to prove account ownership" purpose) rather than
    // adding a new enum value for what is functionally the same check.

    public function forgotMpinRequest()
    {
        $email = trim((string) $this->input('email'));
        $genericMessage = 'If that email belongs to a registered Custodian account, a reset code has just been emailed to it.';

        $credential = (new SuperAdminCredentialModel())->findByEmail($email);
        $party = $credential ? (new PartyModel())->findActiveById($credential['party_id']) : null;
        if (!$party || !(new AuthorizationService())->isSuperAdmin($party['id'])) {
            return $this->apiResponse(['message' => $genericMessage]);
        }

        $emailOtp = $this->accountAuth->requestEmailOtp($credential['email'], 'mpin_reset_email');
        $emailSent = (new EmailNotificationService())->sendOtp($credential['email'], $emailOtp, 'mpin_reset_email');
        $ticketClaims = ['sub' => $party['id'], 'email' => $credential['email']];

        $response = [
            'message' => $genericMessage,
            'dev_email_otp' => $emailOtp,
            'email_sent' => $emailSent,
            'email' => $credential['email'],
        ];

        (new AuditLogService())->log('admin.password_reset_requested', $party['id'], [
            'channel' => 'email', 'emailDeliveredForReal' => $emailSent,
        ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        $response['pending_ticket'] = UserAuthApiService::issuePendingTicket('admin_password_reset_pending', $ticketClaims);
        return $this->apiResponse($response);
    }

    // POST /api/v1/admin/auth/forgot-password/verify  { pending_ticket, email_otp }
    // -> pending_ticket for setNewPassword(), below
    public function forgotMpinVerify()
    {
        $ticket = (string) $this->input('pending_ticket');
        $claims = UserAuthApiService::decodePendingTicket($ticket, 'admin_password_reset_pending');
        if (!$claims) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket. Start the reset again.');
        }

        $emailOtp = trim((string) ($this->input('email_otp') ?? $this->input('otp')));
        if (!$this->accountAuth->verifyEmailOtp($claims['email'], $emailOtp, 'mpin_reset_email')) {
            return $this->jsonError(401, 'invalid_email_otp', 'Incorrect or expired email code.');
        }

        $passwordSetupTicket = UserAuthApiService::issuePendingTicket('admin_password_setup_pending', ['sub' => $claims['sub']]);
        return $this->apiResponse(['pending_ticket' => $passwordSetupTicket]);
    }

    // POST /api/v1/admin/auth/forgot-password/complete  { pending_ticket, new_password }
    public function setNewPassword()
    {
        $ticket = (string) $this->input('pending_ticket');
        $claims = UserAuthApiService::decodePendingTicket($ticket, 'admin_password_setup_pending');
        if (!$claims) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket. Start the reset again.');
        }

        $newPassword = (string) $this->input('new_password');
        if (strlen($newPassword) < 8) {
            return $this->jsonError(422, 'weak_password', 'Password must be at least 8 characters.');
        }

        $this->auth->resetPassword($claims['sub'], $newPassword);
        return $this->apiResponse(['message' => 'Password updated. You can now log in with your new password.']);
    }
}
