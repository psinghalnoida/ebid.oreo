<?php

namespace App\Controllers;

use App\Libraries\SuperAdminAuthService;
use App\Libraries\UserAuthApiService;
use App\Libraries\AuditLogService;

// JWT counterpart of SuperAdminAuthController (BR-04's separate,
// TOTP/email-OTP-verified Super Admin login). Reuses SuperAdminAuthService
// for every actual auth decision — this controller only reshapes it as
// JSON + a JWT carrying roles=['party','super_admin'] instead of a view +
// session markers.
//
// D-130 migration note: still ALSO sets the classic session markers on
// success (dual-write), because AdminController/TenantController/
// UserController/ChargebackController/etc. are not converted to JWT yet
// and still gate on SuperAdminFilter's session check. Safe to drop once
// every superAdmin-filtered controller has a jwtSuperAdmin equivalent —
// tracked as a follow-up migration phase, not done in this pass.
class SuperAdminAuthApiController extends BaseController
{
    private SuperAdminAuthService $auth;
    private UserAuthApiService $tokens;

    public function __construct()
    {
        $this->auth = new SuperAdminAuthService();
        $this->tokens = new UserAuthApiService();
    }

    // POST /api/v1/admin/auth/login  { mobile_number, mpin, totp_code? }
    // twoFactorMode()==='totp' (default): totp_code required, completes
    // in one call. twoFactorMode()==='email_otp': totp_code is ignored,
    // an OTP is emailed, and a pending_ticket is returned for step 2
    // (POST .../login/verify-email) instead of an access token.
    public function login()
    {
        $mobile = trim((string) $this->request->getJsonVar('mobile_number'));
        $mpin = trim((string) $this->request->getJsonVar('mpin'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        if (SuperAdminAuthService::twoFactorMode() === 'email_otp') {
            try {
                $result = $this->auth->requestLoginEmailOtp($mobile, $mpin);
            } catch (\RuntimeException $e) {
                return $this->response->setStatusCode(401)->setJSON(['error' => 'login_failed', 'error_description' => $e->getMessage()]);
            }

            $audit->log('admin.login_email_otp_requested', $result['party']['id'], ['emailDeliveredForReal' => $result['emailSent']], $ip, $userAgent);

            $pendingTicket = UserAuthApiService::issuePendingTicket('admin_login_email_pending', ['sub' => $result['party']['id']]);
            return $this->response->setStatusCode(200)->setJSON([
                'stage' => 'email_otp_required',
                'pending_ticket' => $pendingTicket,
                'email' => $result['party']['recovery_email'],
                'email_sent' => $result['emailSent'],
                // Dev-only convenience — same convention as every other
                // OTP flow in this app; never returned once real SMTP is
                // configured (see EmailNotificationService).
                'dev_otp' => $result['otp'],
            ]);
        }

        $totpCode = trim((string) $this->request->getJsonVar('totp_code'));
        try {
            $party = $this->auth->login($mobile, $mpin, $totpCode);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'login_failed', 'error_description' => $e->getMessage()]);
        }

        return $this->completeLogin($party, $audit, $ip, $userAgent);
    }

    // POST /api/v1/admin/auth/login/verify-email  { pending_ticket, otp }
    public function verifyEmail()
    {
        $ticket = (string) $this->request->getJsonVar('pending_ticket');
        $otp = trim((string) $this->request->getJsonVar('otp'));
        $audit = new AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        $claims = UserAuthApiService::decodePendingTicket($ticket, 'admin_login_email_pending');
        if (!$claims) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'invalid_ticket', 'error_description' => 'Invalid or expired pending_ticket. Start the login again.',
            ]);
        }

        try {
            $party = $this->auth->completeLoginWithEmailOtp($claims['sub'], $otp);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'invalid_otp', 'error_description' => $e->getMessage()]);
        }

        return $this->completeLogin($party, $audit, $ip, $userAgent);
    }

    private function completeLogin(array $party, AuditLogService $audit, string $ip, string $userAgent)
    {
        // Dual-write, see class docblock — keeps unconverted
        // superAdmin-filtered HTML controllers working during migration.
        session()->set('super_admin_totp_verified_at', date('Y-m-d H:i:s'));
        session()->set('super_admin_party_id', $party['id']);
        session()->set('logged_in_party_id', $party['id']);

        $audit->log('admin.login.success', $party['id'], [], $ip, $userAgent);

        $accessToken = $this->tokens->issueAccessToken($party, ['party', 'super_admin']);

        return $this->response->setStatusCode(200)->setJSON([
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
}
