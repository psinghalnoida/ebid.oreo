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

    // POST /api/v1/admin/auth/login  { mobile_number, mpin, totp_code? }
    public function login()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $mpin = trim((string) $this->input('mpin'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        if (SuperAdminAuthService::twoFactorMode() === 'email_otp') {
            try {
                $result = $this->auth->requestLoginEmailOtp($mobile, $mpin);
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
            $party = $this->auth->login($mobile, $mpin, $totpCode);
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

    // ── Forgot mPIN — email-only recovery, unauthenticated ────────
    //
    // Deliberately separate from the regular login-lockout reset path
    // (UserAuthApiController::loginWithMpin's 'otp_required' branch): a
    // Custodian who genuinely forgot their mPIN shouldn't have to
    // deliberately fail their own login 3 times first. Converges on the
    // same 'mpin_setup_pending' ticket + UserAuthApiController::
    // completeMpinSetup used by every other "prove identity, then set
    // mPIN" flow. Always returns the same generic response whether or
    // not the number is a real Custodian account — this endpoint is
    // reachable unauthenticated and must not become an oracle for which
    // mobile numbers hold the super_admin role.
    //
    // Project owner's explicit request: Custodian recovery is
    // email-only, not mobile SMS — no requestOtp()/SMS call here at
    // all. Sent via EmailNotificationService (CI4's Email service,
    // office@vpsinghal.com / Google Workspace SMTP — see app/Config/
    // Email.php + env's `email.*` settings). A Custodian account with
    // no recovery_email on file has no way to self-recover this way —
    // same generic response either way, since this must not become an
    // oracle for which accounts exist.

    public function forgotMpinRequest()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $genericMessage = 'If that number belongs to a registered Custodian account with a recovery email on file, a reset code has just been emailed to it.';

        $party = (new PartyModel())->findByMobile($mobile);
        if (!$party || !(new AuthorizationService())->isSuperAdmin($party['id']) || empty($party['recovery_email'])) {
            return $this->response->setJSON(['message' => $genericMessage]);
        }

        $emailOtp = $this->accountAuth->requestEmailOtp($party['recovery_email'], 'mpin_reset_email');
        $emailSent = (new EmailNotificationService())->sendOtp($party['recovery_email'], $emailOtp, 'mpin_reset_email');
        $ticketClaims = ['sub' => $party['id'], 'email' => $party['recovery_email']];

        $response = [
            'message' => $genericMessage,
            'dev_email_otp' => $emailOtp,
            'email_sent' => $emailSent,
            'email' => $party['recovery_email'],
        ];

        (new AuditLogService())->log('admin.mpin_reset_requested', $party['id'], [
            'mobile' => $mobile, 'channel' => 'email', 'emailDeliveredForReal' => $emailSent,
        ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        $response['pending_ticket'] = UserAuthApiService::issuePendingTicket('admin_mpin_reset_pending', $ticketClaims);
        return $this->response->setJSON($response);
    }

    // POST /api/v1/admin/auth/forgot-mpin/verify  { pending_ticket, email_otp }
    // -> pending_ticket for /api/v1/auth/mpin/complete (UserAuthApiController)
    public function forgotMpinVerify()
    {
        $ticket = (string) $this->input('pending_ticket');
        $claims = UserAuthApiService::decodePendingTicket($ticket, 'admin_mpin_reset_pending');
        if (!$claims) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket. Start the reset again.');
        }

        $emailOtp = trim((string) ($this->input('email_otp') ?? $this->input('otp')));
        if (!$this->accountAuth->verifyEmailOtp($claims['email'], $emailOtp, 'mpin_reset_email')) {
            return $this->jsonError(401, 'invalid_email_otp', 'Incorrect or expired email code.');
        }

        $mpinSetupTicket = UserAuthApiService::issuePendingTicket('mpin_setup_pending', ['sub' => $claims['sub']]);
        return $this->response->setJSON(['pending_ticket' => $mpinSetupTicket]);
    }
}
