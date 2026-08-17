<?php

namespace App\Controllers;

use App\Libraries\SuperAdminAuthService;
use App\Libraries\AuthService;
use App\Libraries\AuthorizationService;
use App\Libraries\EmailNotificationService;
use App\Libraries\AuditLogService;
use App\Models\PartyModel;

class SuperAdminAuthController extends BaseController
{
    private SuperAdminAuthService $auth;
    private AuthService $accountAuth;
    private AuthorizationService $authz;
    private EmailNotificationService $emailer;
    private PartyModel $partyModel;

    public function __construct()
    {
        $this->auth = new SuperAdminAuthService();
        $this->accountAuth = new AuthService();
        $this->authz = new AuthorizationService();
        $this->emailer = new EmailNotificationService();
        $this->partyModel = new PartyModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // Setup requires being logged in normally first (proves you control
    // the account the role was granted to) — this only enrolls 2FA, it
    // does not grant the super_admin role itself (that's grant:super-admin).
    public function setupTotpForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        // PR-17: was the caller genuinely authenticated via the
        // isolated /admin/login TOTP-gated path (proving they hold the
        // CURRENT device), not just the regular mobile+mPIN session?
        $isolatedVerified = session()->get('super_admin_totp_verified_at') !== null;

        try {
            $setup = $this->auth->beginTotpSetup($partyId, $isolatedVerified);
        } catch (\RuntimeException $e) {
            return redirect()->to('/')->with('error', $e->getMessage());
        }

        return view('admin/setup_totp', ['title' => 'Set Up Super Admin 2FA — AdwitiX', 'setup' => $setup]);
    }

    public function setupTotpSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $code = $this->request->getPost('code');
        try {
            $confirmed = $this->auth->confirmTotpSetup($partyId, $code);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/setup-totp')->with('error', $e->getMessage());
        }

        if (!$confirmed) {
            return redirect()->to('/admin/setup-totp')->with('error', 'Invalid code — check your authenticator app and try again.');
        }

        // Shown exactly once, rendered directly from this request — the
        // plain codes are never put in session/flash, only the bcrypt
        // hashes persist (in super_admin_backup_code).
        return view('admin/setup_totp_backup_codes', [
            'title' => 'Save Your Backup Codes — AdwitiX',
            'codes' => $confirmed,
        ]);
    }

    public function loginForm()
    {
        return view('admin/login', [
            'title' => 'Super Admin Login — AdwitiX',
            'message' => session()->getFlashdata('message'),
            // D-128: TEMPORARY toggle — see SuperAdminAuthService::twoFactorMode()'s
            // own doc comment. Drives whether the login form shows a TOTP
            // field or explains a code will be emailed instead.
            'twoFactorMode' => SuperAdminAuthService::twoFactorMode(),
        ]);
    }

    public function loginSubmit()
    {
        $mobile = trim($this->request->getPost('mobile_number'));
        $mpin = trim($this->request->getPost('mpin'));

        // D-128: email-OTP mode is a genuine two-step flow (the code
        // doesn't exist yet at the time of this POST — it has to be
        // generated and sent first), unlike TOTP where the code already
        // exists on the caller's device. So this branch validates
        // mobile+mPIN, sends the email, and redirects to a second step
        // instead of completing login in one request.
        if (SuperAdminAuthService::twoFactorMode() === 'email_otp') {
            try {
                $result = $this->auth->requestLoginEmailOtp($mobile, $mpin);
            } catch (\RuntimeException $e) {
                return view('admin/login', ['title' => 'Super Admin Login — AdwitiX', 'error' => $e->getMessage(), 'twoFactorMode' => 'email_otp']);
            }

            session()->set('admin_login_email_pending_party_id', $result['party']['id']);
            (new AuditLogService())->log('admin.login_email_otp_requested', $result['party']['id'], [
                'emailDeliveredForReal' => $result['emailSent'],
            ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

            return view('admin/login_verify_email', [
                'title' => 'Verify Login Code — AdwitiX',
                'email' => $result['party']['recovery_email'],
                'emailSent' => $result['emailSent'],
                // Dev-mode on-screen fallback — same honest convention
                // as every other OTP flow in this app (devOtp/
                // devEmailOtp): no real SMTP is configured in this
                // environment, so without this the flow would be
                // untestable here.
                'devOtp' => $result['otp'],
            ]);
        }

        $totpCode = trim($this->request->getPost('totp_code'));
        try {
            $party = $this->auth->login($mobile, $mpin, $totpCode);
        } catch (\RuntimeException $e) {
            return view('admin/login', ['title' => 'Super Admin Login — AdwitiX', 'error' => $e->getMessage(), 'twoFactorMode' => 'totp']);
        }

        $this->completeLogin($party['id']);
        return redirect()->to('/admin');
    }

    // D-128: stage 2 of the email-OTP login path.
    public function loginVerifyEmailForm()
    {
        if (!session()->get('admin_login_email_pending_party_id')) {
            return redirect()->to('/admin/login');
        }
        return view('admin/login_verify_email', ['title' => 'Verify Login Code — AdwitiX']);
    }

    public function loginVerifyEmailSubmit()
    {
        $partyId = session()->get('admin_login_email_pending_party_id');
        if (!$partyId) {
            return redirect()->to('/admin/login');
        }

        $otp = trim($this->request->getPost('otp'));
        try {
            $party = $this->auth->completeLoginWithEmailOtp($partyId, $otp);
        } catch (\RuntimeException $e) {
            return view('admin/login_verify_email', ['title' => 'Verify Login Code — AdwitiX', 'error' => $e->getMessage()]);
        }

        session()->remove('admin_login_email_pending_party_id');
        $this->completeLogin($party['id']);
        return redirect()->to('/admin');
    }

    // Shared by both the TOTP and email-OTP paths — the session markers
    // that actually constitute "logged in as Super Admin" are identical
    // either way; only how the second factor was verified differs.
    private function completeLogin(string $partyId): void
    {
        // Distinct session markers from regular login — this is the real
        // separate-login-path security boundary the SuperAdminFilter checks.
        session()->set('super_admin_totp_verified_at', date('Y-m-d H:i:s'));
        session()->set('super_admin_party_id', $partyId);
        session()->set('logged_in_party_id', $partyId); // also usable as a regular session
    }

    public function logout()
    {
        session()->remove(['super_admin_totp_verified_at', 'super_admin_party_id']);
        return redirect()->to('/admin/login');
    }

    // ── Forgot mPIN — dual-channel recovery, unauthenticated ──────
    //
    // Deliberately separate from AuthController's own mPIN-reset path
    // (which only triggers after BR-02's 3-strike lockout): a Custodian
    // who genuinely forgot their mPIN shouldn't have to deliberately
    // fail their own login 3 times first to reach a reset flow. Reuses
    // the exact same AuthService OTP primitives (requestOtp/
    // requestEmailOtp/verifyOtp/verifyEmailOtp/setMpin) the regular
    // flow already uses — no parallel OTP logic to keep in sync.
    //
    // Gated on the account actually holding super_admin before ANY code
    // is sent, and always returns the same generic response either way
    // — this form is reachable without being logged in, so it must not
    // become a way to fire OTPs at an arbitrary mobile number or leak
    // which numbers are Custodian accounts.

    public function forgotMpinForm()
    {
        return view('admin/forgot_mpin', ['title' => 'Forgot mPIN — AdwitiX']);
    }

    public function forgotMpinSubmit()
    {
        $mobile = trim($this->request->getPost('mobile_number'));
        $genericMessage = 'If that number belongs to a registered Custodian account, a reset code has just been sent to it (and to the recovery email on file, if one is set).';

        $party = $this->partyModel->findByMobile($mobile);
        if (!$party || !$this->authz->isSuperAdmin($party['id'])) {
            // Same response whether the number doesn't exist, isn't a
            // Custodian account, or genuinely is one — no OTP sent in
            // the first two cases, but the caller can't tell which.
            return view('admin/forgot_mpin', ['title' => 'Forgot mPIN — AdwitiX', 'info' => $genericMessage]);
        }

        $mobileOtp = $this->accountAuth->requestOtp($mobile, 'mpin_reset');
        session()->set('admin_mpin_reset_party_id', $party['id']);
        session()->set('admin_mpin_reset_mobile', $mobile);

        $emailOtp = null;
        $emailSent = false;
        if (!empty($party['recovery_email'])) {
            $emailOtp = $this->accountAuth->requestEmailOtp($party['recovery_email']);
            $emailSent = $this->emailer->sendOtp($party['recovery_email'], $emailOtp, 'mpin_reset_email');
            session()->set('admin_mpin_reset_email', $party['recovery_email']);
        }

        (new AuditLogService())->log('admin.mpin_reset_requested', $party['id'], [
            'mobile' => $mobile, 'hasRecoveryEmail' => !empty($party['recovery_email']), 'emailDeliveredForReal' => $emailSent,
        ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        return view('admin/forgot_mpin_verify', [
            'title' => 'Verify Reset Code — AdwitiX',
            'mobile' => $mobile,
            'email' => $party['recovery_email'] ?? null,
            // Dev-mode on-screen fallback — same honest convention as
            // AuthController's devOtp/devEmailOtp: no real SMTP is
            // configured in this environment (see
            // EmailNotificationService's own doc comment), so without
            // this the flow would be untestable here. $emailSent is
            // still surfaced so it's visible whether a real send was
            // even attempted, not just assumed.
            'devOtp' => $mobileOtp,
            'devEmailOtp' => $emailOtp,
            'emailSent' => $emailSent,
        ]);
    }

    public function forgotMpinVerifySubmit()
    {
        $partyId = session()->get('admin_mpin_reset_party_id');
        $mobile = session()->get('admin_mpin_reset_mobile');
        $email = session()->get('admin_mpin_reset_email');

        if (!$partyId || !$mobile) {
            return redirect()->to('/admin/forgot-mpin');
        }

        $otp = trim($this->request->getPost('otp'));
        if (!$this->accountAuth->verifyOtp($mobile, 'mpin_reset', $otp)) {
            return view('admin/forgot_mpin_verify', [
                'title' => 'Verify Reset Code — AdwitiX', 'mobile' => $mobile, 'email' => $email,
                'error' => 'Incorrect or expired mobile code. Please try again.',
            ]);
        }

        if ($email) {
            $emailOtp = trim($this->request->getPost('email_otp'));
            if (!$this->accountAuth->verifyEmailOtp($email, $emailOtp)) {
                return view('admin/forgot_mpin_verify', [
                    'title' => 'Verify Reset Code — AdwitiX', 'mobile' => $mobile, 'email' => $email,
                    'error' => 'Mobile code correct, but the email code was incorrect or expired. Both are required together.',
                ]);
            }
        }

        session()->set('admin_mpin_reset_verified_party_id', $partyId);
        session()->remove(['admin_mpin_reset_party_id', 'admin_mpin_reset_mobile', 'admin_mpin_reset_email']);

        return view('admin/forgot_mpin_set_mpin', ['title' => 'Set a New mPIN — AdwitiX']);
    }

    public function forgotMpinSetMpinSubmit()
    {
        $partyId = session()->get('admin_mpin_reset_verified_party_id');
        if (!$partyId) {
            return redirect()->to('/admin/forgot-mpin');
        }

        $mpin = trim($this->request->getPost('mpin'));
        try {
            $this->accountAuth->setMpin($partyId, $mpin);
        } catch (\RuntimeException $e) {
            return view('admin/forgot_mpin_set_mpin', ['title' => 'Set a New mPIN — AdwitiX', 'error' => $e->getMessage()]);
        }

        (new AuditLogService())->log('admin.mpin_reset_completed', $partyId, [], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        session()->remove('admin_mpin_reset_verified_party_id');

        return redirect()->to('/admin/login')->with('message', 'mPIN reset. Log in with your new mPIN and authenticator code.');
    }
}
