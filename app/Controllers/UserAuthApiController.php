<?php

namespace App\Controllers;

use App\Libraries\AuthService;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;

// REST/JWT login API for mobile clients — mobile OTP request, OTP verify,
// profile submission, JWT issuance. See UserAuthApiService for the flow
// details; this controller is just request parsing + response shaping.
class UserAuthApiController extends BaseController
{
    private UserAuthApiService $service;

    public function __construct()
    {
        $this->service = new UserAuthApiService();
    }

    // POST /api/v1/auth/otp/request  { mobile_number }
    // Step 1: sends (or, since the SMS provider is stubbed, returns) an
    // OTP for the given mobile number.
    public function requestOtp()
    {
        $mobile = trim((string) $this->request->getJsonVar('mobile_number'));

        if (!AuthService::isValidIndianMobile($mobile)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'invalid_mobile_number',
                'error_description' => 'Expected a 10-digit Indian mobile number in +91XXXXXXXXXX format.',
            ]);
        }

        $otp = $this->service->requestLoginOtp($mobile);

        return $this->response->setStatusCode(200)->setJSON([
            'message' => 'OTP sent.',
            // Dev-only convenience: the SMS provider is stubbed (see
            // AuthService::requestOtp docblock) — never returned in
            // production once a real SMS provider is wired up.
            'dev_otp' => $otp,
        ]);
    }

    // POST /api/v1/auth/otp/verify  { mobile_number, otp }
    // Step 2: verifies the OTP and returns a short-lived "otp ticket" JWT
    // (needed by step 3) plus the existing profile, if any, to prefill.
    public function verifyOtp()
    {
        $mobile = trim((string) $this->request->getJsonVar('mobile_number'));
        $otp = trim((string) $this->request->getJsonVar('otp'));

        if ($mobile === '' || $otp === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'invalid_request', 'error_description' => 'mobile_number and otp are both required.',
            ]);
        }

        try {
            $result = $this->service->verifyLoginOtp($mobile, $otp);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'invalid_otp', 'error_description' => $e->getMessage(),
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON($result);
    }

    // POST /api/v1/auth/submit  { otp_ticket, mobile_number, full_name, email }
    // Step 3: submits the user-info form and issues the access JWT.
    public function submit()
    {
        $otpTicket = (string) $this->request->getJsonVar('otp_ticket');
        $mobile = trim((string) $this->request->getJsonVar('mobile_number'));
        $fullName = (string) $this->request->getJsonVar('full_name');
        $email = (string) $this->request->getJsonVar('email');
        $entityType = $this->request->getJsonVar('entity_type');

        if ($otpTicket === '' || $mobile === '') {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => 'invalid_request', 'error_description' => 'otp_ticket and mobile_number are both required.',
            ]);
        }

        try {
            $result = $this->service->completeLogin($otpTicket, $mobile, [
                'full_name' => $fullName,
                'email' => $email,
                'entity_type' => $entityType,
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON([
                'error' => 'invalid_otp_ticket', 'error_description' => $e->getMessage(),
            ]);
        }

        return $this->response->setStatusCode(200)->setJSON($result);
    }

    // ── BR-02 mPIN registration/login (D-137, replaces AuthController) ──

    // POST /api/v1/auth/register/otp/request  { mobile_number }
    public function registerRequestOtp()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        if (!AuthService::isValidIndianMobile($mobile)) {
            return $this->jsonError(422, 'invalid_mobile_number', 'Expected a 10-digit Indian mobile number in +91XXXXXXXXXX format.');
        }
        try {
            $otp = $this->service->registerRequestOtp($mobile);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'request_failed', $e->getMessage());
        }
        return $this->response->setJSON(['message' => 'OTP sent.', 'dev_otp' => $otp]);
    }

    // POST /api/v1/auth/register/otp/verify  { mobile_number, otp }
    // -> pending_ticket for /api/v1/auth/mpin/complete
    public function registerVerifyOtp()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $otp = trim((string) $this->input('otp'));
        try {
            $ticket = $this->service->registerVerifyOtp($mobile, $otp);
        } catch (\RuntimeException $e) {
            return $this->jsonError(401, 'invalid_otp', $e->getMessage());
        }
        return $this->response->setJSON(['pending_ticket' => $ticket]);
    }

    // POST /api/v1/auth/login  { mobile_number, mpin }
    // Three shapes back, mirroring AuthService::authenticateWithMpin():
    // {status:"ok", access_token, ...}, {status:"otp_required",
    // pending_ticket, ...}, or {status:"invalid_mpin", attemptsRemaining}.
    public function loginWithMpin()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        $mpin = trim((string) $this->input('mpin'));
        $audit = new \App\Libraries\AuditLogService();
        $ip = $this->request->getIPAddress();
        $userAgent = (string) $this->request->getUserAgent();

        try {
            $result = $this->service->loginWithMpin($mobile, $mpin);
        } catch (\RuntimeException $e) {
            $audit->log('auth.login.failed', null, ['mobile' => $mobile, 'reason' => $e->getMessage()], $ip, $userAgent);
            return $this->jsonError(401, 'login_failed', $e->getMessage());
        }

        $eventsByStatus = ['ok' => 'auth.login.success', 'otp_required' => 'auth.login.otp_required', 'invalid_mpin' => 'auth.login.invalid_mpin'];
        $audit->log($eventsByStatus[$result['status']], $result['party']['id'] ?? null, ['mobile' => $mobile], $ip, $userAgent);

        if ($result['status'] === 'invalid_mpin') {
            return $this->jsonError(401, 'invalid_mpin', "Incorrect mPIN. {$result['attemptsRemaining']} attempt(s) remaining before OTP verification is required.");
        }

        return $this->response->setJSON($result);
    }

    // POST /api/v1/auth/forgot-password  { mobile_number }
    // Standalone forgot-mPIN entry point (no failed-login lockout
    // needed first) — see UserAuthApiService::requestForgotPassword().
    // -> pending_ticket for loginVerifyResetOtp() below (same ticket
    // type as the lockout-triggered reset), then mpin/complete.
    public function forgotPassword()
    {
        $mobile = trim((string) $this->input('mobile_number'));
        if (!AuthService::isValidIndianMobile($mobile)) {
            return $this->jsonError(422, 'invalid_mobile_number', 'Expected a 10-digit Indian mobile number in +91XXXXXXXXXX format.');
        }

        $result = $this->service->requestForgotPassword($mobile);
        return $this->response->setJSON($result);
    }

    // POST /api/v1/auth/login/verify-reset-otp  { pending_ticket, otp, email_otp? }
    // -> pending_ticket for /api/v1/auth/mpin/complete
    public function loginVerifyResetOtp()
    {
        $ticket = (string) $this->input('pending_ticket');
        $otp = trim((string) $this->input('otp'));
        $emailOtp = $this->input('email_otp') !== null ? trim((string) $this->input('email_otp')) : null;

        try {
            $mpinSetupTicket = $this->service->verifyMpinResetOtp($ticket, $otp, $emailOtp);
        } catch (\RuntimeException $e) {
            return $this->jsonError(401, 'invalid_otp', $e->getMessage());
        }
        return $this->response->setJSON(['pending_ticket' => $mpinSetupTicket]);
    }

    // POST /api/v1/auth/mpin/complete  { pending_ticket, mpin }
    // Shared final step for registration, login-reset, and
    // SuperAdminAuthApiController's forgot-mPIN.
    public function completeMpinSetup()
    {
        $ticket = (string) $this->input('pending_ticket');
        $mpin = trim((string) $this->input('mpin'));

        try {
            $result = $this->service->completeMpinSetup($ticket, $mpin);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'mpin_setup_failed', $e->getMessage());
        }
        return $this->response->setJSON($result);
    }

    // GET /api/v1/auth/me  (filter: jwtAuth)
    // Returns the authenticated user's data — the "get user data with JWT
    // token" step, callable again on any later request with the same token.
    public function me()
    {
        $party = UserAuthContext::party();

        return $this->response->setStatusCode(200)->setJSON([
            'party' => [
                'id' => $party['id'],
                'mobile_number' => $party['mobile_number'],
                'full_name' => $party['full_name'] ?? null,
                'email' => $party['recovery_email'] ?? null,
                'entity_type' => $party['entity_type'] ?? null,
                'kyc_status' => $party['kyc_status'] ?? null,
            ],
        ]);
    }
}
