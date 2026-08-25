<?php

namespace App\Libraries;

use App\Models\PartyModel;

// REST/JWT login flow for mobile clients, distinct from the browser
// session flow in AuthController (BR-02) and the server-to-server Tenant
// API in ApiCredentialService (BR-62/64). Three steps, each producing the
// token the next one needs so the API stays stateless (no PHP session):
//
//   1. requestLoginOtp()   mobile -> OTP sent (AuthService, purpose=api_login)
//   2. verifyLoginOtp()    mobile+otp -> short-lived "otp ticket" JWT, plus
//                          the party's existing profile if one is on file
//                          (so the client can prefill the info form)
//   3. completeLogin()     otp ticket + name/email (+ optional entityType)
//                          -> upserts the party, issues the access JWT
class UserAuthApiService
{
    // Deliberately short — this ticket only proves "this mobile number's
    // OTP was verified a moment ago", not "this session is logged in".
    private const OTP_TICKET_TTL_SECONDS = 600; // 10 minutes
    private const ACCESS_TOKEN_TTL_SECONDS = 86400; // 24 hours

    private const OTP_TICKET_SECRET_ENV = 'EBIDHUB_JWT_OTP_TICKET_SECRET';
    private const OTP_TICKET_DEV_SECRET = 'dev-only-otp-ticket-secret-change-in-production';
    private const ACCESS_TOKEN_SECRET_ENV = 'EBIDHUB_JWT_ACCESS_SECRET';
    private const ACCESS_TOKEN_DEV_SECRET = 'dev-only-jwt-access-secret-change-in-production';

    private AuthService $auth;
    private PartyModel $partyModel;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->partyModel = new PartyModel();
    }

    // Step 1. Returns the plain OTP only as a dev/testing convenience —
    // same caveat as AuthService::requestOtp() itself (SMS provider is
    // stubbed); a real deployment would hand this to the SMS provider and
    // never return it in the response body.
    public function requestLoginOtp(string $mobileNumber): string
    {
        return $this->auth->requestOtp($mobileNumber, 'api_login');
    }

    // Step 2. Throws on an incorrect/expired OTP so the controller can
    // return a clean 401 without a stack trace.
    public function verifyLoginOtp(string $mobileNumber, string $otp): array
    {
        if (!$this->auth->verifyOtp($mobileNumber, 'api_login', $otp)) {
            throw new \RuntimeException('Incorrect or expired OTP.');
        }

        $ticket = JwtService::encode([
            'typ' => 'otp_ticket',
            'mobile' => $mobileNumber,
            'iat' => time(),
            'exp' => time() + self::OTP_TICKET_TTL_SECONDS,
        ], self::OTP_TICKET_SECRET_ENV, self::OTP_TICKET_DEV_SECRET);

        // Existing party's profile, if any, so the client can prefill the
        // info form on step 3 instead of asking a returning user to
        // retype their name/email from scratch.
        $existingParty = $this->partyModel->findByMobile($mobileNumber);

        return [
            'otp_ticket' => $ticket,
            'expires_in' => self::OTP_TICKET_TTL_SECONDS,
            'is_new_user' => $existingParty === null,
            'party' => $existingParty ? self::toProfile($existingParty) : null,
        ];
    }

    // Step 3. $otpTicket must be a still-valid ticket from step 2, and its
    // "mobile" claim must match $mobileNumber — the client can't submit a
    // profile for a different mobile number than the one it verified.
    public function completeLogin(string $otpTicket, string $mobileNumber, array $profile): array
    {
        $claims = JwtService::decode($otpTicket, self::OTP_TICKET_SECRET_ENV, self::OTP_TICKET_DEV_SECRET);
        if (!$claims || ($claims['typ'] ?? null) !== 'otp_ticket') {
            throw new \RuntimeException('Invalid or expired OTP ticket. Please verify the OTP again.');
        }
        if (($claims['mobile'] ?? null) !== $mobileNumber) {
            throw new \RuntimeException('OTP ticket does not match the given mobile number.');
        }

        $fullName = trim((string) ($profile['full_name'] ?? ''));
        $email = trim((string) ($profile['email'] ?? ''));
        if ($fullName === '') {
            throw new \RuntimeException('full_name is required.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('email must be a valid email address.');
        }

        $party = $this->partyModel->findByMobile($mobileNumber);
        if ($party) {
            $this->partyModel->update($party['id'], [
                'full_name' => $fullName,
                'recovery_email' => $email !== '' ? $email : $party['recovery_email'],
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $party = $this->partyModel->createParty($mobileNumber, $profile['entity_type'] ?? 'individual');
            $this->partyModel->update($party['id'], [
                'mobile_verified_at' => date('Y-m-d H:i:s'),
                'full_name' => $fullName,
                'recovery_email' => $email !== '' ? $email : null,
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $party = $this->partyModel->find($party['id']);

        $accessToken = $this->issueAccessToken($party);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'party' => self::toProfile($party),
        ];
    }

    public function issueAccessToken(array $party): string
    {
        return JwtService::encode([
            'typ' => 'access',
            'sub' => $party['id'],
            'mobile' => $party['mobile_number'],
            'iat' => time(),
            'exp' => time() + self::ACCESS_TOKEN_TTL_SECONDS,
        ], self::ACCESS_TOKEN_SECRET_ENV, self::ACCESS_TOKEN_DEV_SECRET);
    }

    // Used by the JWT auth filter to authenticate subsequent requests.
    public static function validateAccessToken(string $token): ?array
    {
        $claims = JwtService::decode($token, self::ACCESS_TOKEN_SECRET_ENV, self::ACCESS_TOKEN_DEV_SECRET);
        if (!$claims || ($claims['typ'] ?? null) !== 'access' || !isset($claims['sub'])) {
            return null;
        }
        return $claims;
    }

    private static function toProfile(array $party): array
    {
        return [
            'id' => $party['id'],
            'mobile_number' => $party['mobile_number'],
            'full_name' => $party['full_name'] ?? null,
            'email' => $party['recovery_email'] ?? null,
            'entity_type' => $party['entity_type'] ?? null,
            'kyc_status' => $party['kyc_status'] ?? null,
        ];
    }
}
