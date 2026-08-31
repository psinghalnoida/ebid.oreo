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

        // Render the provisioning URI as a scannable QR code here, server
        // side, rather than leaving callers to find their own way to turn
        // an otpauth:// URI into an image — same endroid/qr-code usage as
        // ChronicleController's verification QR. Purely a display
        // convenience: the secret/provisioningUri are still returned
        // as-is for manual entry or a caller that wants to render its own.
        $qrResult = (new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $setup['provisioningUri'], size: 240, margin: 8,
        ))->build();
        $setup['qrCodeDataUri'] = $qrResult->getDataUri();

        return $this->response->setJSON(['setup' => $setup]);
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
        return $this->response->setJSON(['backupCodes' => $confirmed]);
    }

    // ── Login (BR-04) ────────────────────────────────────────────────

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
            return $this->response->setJSON([
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

    private function completeLogin(array $party, AuditLogService $audit, string $ip, string $userAgent)
    {
        $audit->log('admin.login.success', $party['id'], [], $ip, $userAgent);
        $accessToken = $this->tokens->issueAccessToken($party, ['party', 'super_admin']);

        return $this->response->setJSON([
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
            return $this->response->setJSON(['message' => $genericMessage]);
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
        return $this->response->setJSON($response);
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
        return $this->response->setJSON(['pending_ticket' => $passwordSetupTicket]);
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
        return $this->response->setJSON(['message' => 'Password updated. You can now log in with your new password.']);
    }
}
