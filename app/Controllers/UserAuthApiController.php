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
