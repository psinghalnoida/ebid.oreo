<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Libraries\EmailNotificationService;

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
        $reason = $this->auth->verifyOtpWithReason($mobileNumber, 'api_login', $otp);
        if ($reason !== 'ok') {
            throw new \RuntimeException(match ($reason) {
                'no_active_otp' => 'No OTP was requested for this mobile number, or a newer OTP request has already replaced it — request a fresh OTP and use the latest one.',
                'expired' => 'This OTP has expired. Request a new one.',
                'locked_out' => 'Too many incorrect attempts for this OTP. Request a new one.',
                default => 'Incorrect OTP.',
            });
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

    // $roles defaults to the plain party role. SuperAdminAuthApiController
    // passes ['party', 'super_admin'] after its own, separate TOTP/email-
    // OTP-verified login — mirrors the "distinct session marker" boundary
    // SuperAdminFilter enforces today (holding the super_admin DB role
    // alone was never enough; the same holds for this claim).
    public function issueAccessToken(array $party, array $roles = ['party']): string
    {
        return JwtService::encode([
            'typ' => 'access',
            'sub' => $party['id'],
            'mobile' => $party['mobile_number'],
            'roles' => $roles,
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

    // Generic short-lived "step N needs step N-1's proof" ticket, on the
    // same OTP-ticket secret as verifyLoginOtp()'s ticket — used for any
    // multi-request flow that needs a stateless stand-in for a PHP
    // session (e.g. SuperAdminAuthApiController's email-OTP login stage).
    // $typ scopes tickets from different flows apart from each other, the
    // same way OTP purposes already scope otp_verification rows apart.
    public static function issuePendingTicket(string $typ, array $claims, int $ttlSeconds = self::OTP_TICKET_TTL_SECONDS): string
    {
        return JwtService::encode(
            ['typ' => $typ, 'iat' => time(), 'exp' => time() + $ttlSeconds] + $claims,
            self::OTP_TICKET_SECRET_ENV, self::OTP_TICKET_DEV_SECRET
        );
    }

    public static function decodePendingTicket(string $token, string $expectedTyp): ?array
    {
        $claims = JwtService::decode($token, self::OTP_TICKET_SECRET_ENV, self::OTP_TICKET_DEV_SECRET);
        if (!$claims || ($claims['typ'] ?? null) !== $expectedTyp) {
            return null;
        }
        return $claims;
    }

    // ── BR-02 mPIN registration/login (D-137: JWT replacement for the
    // former AuthController) ────────────────────────────────────────
    //
    // A separate flow from the OTP-only quick-login above (requestLoginOtp/
    // verifyLoginOtp/completeLogin): this is the platform's real BR-02
    // identity — mobile + a 4-digit mPIN with a 3-strike lockout, not a
    // fresh OTP on every login. All three of registration's OTP-verified
    // step, login's lockout-triggered reset, and Super Admin's forgot-mPIN
    // (SuperAdminAuthApiController) converge on the same
    // 'mpin_setup_pending' ticket + completeMpinSetup() below, since
    // "prove who you are, then set an mPIN" is identical in each case —
    // only how the proof happened differs.

    // Registration step 1.
    public function registerRequestOtp(string $mobileNumber): string
    {
        return $this->auth->requestOtp($mobileNumber, 'registration');
    }

    // Registration step 2 — verifies the OTP, creates the party record
    // (BR-02: idempotent if one already exists for this mobile), and
    // returns a ticket for step 3 (completeMpinSetup).
    public function registerVerifyOtp(string $mobileNumber, string $otp): string
    {
        if (!$this->auth->verifyOtp($mobileNumber, 'registration', $otp)) {
            throw new \RuntimeException('Incorrect or expired OTP.');
        }
        $party = $this->auth->completeRegistration($mobileNumber);
        return self::issuePendingTicket('mpin_setup_pending', ['sub' => $party['id']]);
    }

    // Login step 1. Mirrors AuthService::authenticateWithMpin()'s three
    // outcomes:
    //  - 'ok': access token issued directly.
    //  - 'otp_required': 3-strike lockout hit — an OTP (dual-channel with
    //    email, if on file) is sent and a ticket returned for step 2
    //    (verifyMpinResetOtp).
    //  - 'invalid_mpin': wrong mPIN, lockout not yet hit.
    public function loginWithMpin(string $mobileNumber, string $mpin): array
    {
        $result = $this->auth->authenticateWithMpin($mobileNumber, $mpin);

        if ($result['status'] === 'ok') {
            $this->partyModel->update($result['party']['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
            return [
                'status' => 'ok',
                'access_token' => $this->issueAccessToken($result['party']),
                'token_type' => 'Bearer',
                'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
                'party' => self::toProfile($result['party']),
            ];
        }

        if ($result['status'] === 'otp_required') {
            $party = $this->partyModel->find($result['partyId']);
            $otp = $this->auth->requestOtp($mobileNumber, 'mpin_reset');
            $ticketClaims = ['sub' => $result['partyId'], 'mobile' => $mobileNumber];

            $response = ['status' => 'otp_required', 'dev_otp' => $otp];
            // Dual-channel: both mobile and email OTP required together,
            // per the account owner's explicit request.
            if (!empty($party['recovery_email'])) {
                $ticketClaims['email'] = $party['recovery_email'];
                $response['dev_email_otp'] = $this->auth->requestEmailOtp($party['recovery_email']);
                $response['email'] = $party['recovery_email'];
            }
            $response['pending_ticket'] = self::issuePendingTicket('mpin_reset_otp_pending', $ticketClaims);
            return $response;
        }

        // 'invalid_mpin'
        return ['status' => 'invalid_mpin', 'attemptsRemaining' => $result['attemptsRemaining']];
    }

    // ── Forgot password (mPIN), unauthenticated ───────────────────────
    //
    // Standalone counterpart of SuperAdminAuthApiController::
    // forgotMpinRequest() for regular users/Bidders: a user who forgot
    // their mPIN shouldn't have to deliberately fail login 3 times to
    // reach loginWithMpin()'s 'otp_required' branch. Produces the same
    // 'mpin_reset_otp_pending' ticket type that branch does, so it
    // converges on the very same verifyMpinResetOtp()/completeMpinSetup()
    // steps as the lockout-triggered reset. Always returns the same
    // generic message whether or not the mobile number is registered —
    // this endpoint is reachable unauthenticated and must not become an
    // oracle for which mobile numbers hold an account.
    public function requestForgotPassword(string $mobileNumber): array
    {
        $genericMessage = 'If that number belongs to a registered account, a reset code has just been sent to it (and to the recovery email on file, if one is set).';

        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || empty($party['mpin_hash'])) {
            return ['message' => $genericMessage];
        }

        $otp = $this->auth->requestOtp($mobileNumber, 'mpin_reset');
        $ticketClaims = ['sub' => $party['id'], 'mobile' => $mobileNumber];

        $response = ['message' => $genericMessage, 'dev_otp' => $otp];
        // Dual-channel: both mobile and email OTP required together, same
        // as the lockout-triggered reset and Custodian forgot-password.
        if (!empty($party['recovery_email'])) {
            $ticketClaims['email'] = $party['recovery_email'];
            $response['dev_email_otp'] = $this->auth->requestEmailOtp($party['recovery_email']);
            $response['email_sent'] = (new EmailNotificationService())->sendOtp($party['recovery_email'], $response['dev_email_otp'], 'mpin_reset_email');
            $response['email'] = $party['recovery_email'];
        }

        $response['pending_ticket'] = self::issuePendingTicket('mpin_reset_otp_pending', $ticketClaims);
        return $response;
    }

    // Login step 2 (only reached via the 'otp_required' branch above).
    public function verifyMpinResetOtp(string $pendingTicket, string $otp, ?string $emailOtp): string
    {
        $claims = self::decodePendingTicket($pendingTicket, 'mpin_reset_otp_pending');
        if (!$claims) {
            throw new \RuntimeException('Invalid or expired pending_ticket. Start the login again.');
        }

        if (!$this->auth->verifyOtp($claims['mobile'], 'mpin_reset', $otp)) {
            throw new \RuntimeException('Incorrect or expired mobile OTP.');
        }
        // Dual-channel: both codes required together once a recovery
        // email is on file.
        if (!empty($claims['email'])) {
            if (!$this->auth->verifyEmailOtp($claims['email'], (string) $emailOtp)) {
                throw new \RuntimeException('Mobile OTP correct, but the email OTP was incorrect or expired. Both are required together.');
            }
        }

        return self::issuePendingTicket('mpin_setup_pending', ['sub' => $claims['sub']]);
    }

    // Shared step 3 for registration, login-reset, AND
    // SuperAdminAuthApiController's forgot-mPIN (all three produce a
    // 'mpin_setup_pending' ticket from their own verification path).
    // Deliberately does NOT grant the super_admin role claim even when
    // reached via the admin forgot-mPIN path — resetting the mPIN proves
    // mobile+email control, not the separate TOTP/email-OTP second
    // factor BR-04's real Super Admin login requires, so the caller
    // logs in again afterward rather than being auto-elevated here.
    public function completeMpinSetup(string $pendingTicket, string $mpin): array
    {
        $claims = self::decodePendingTicket($pendingTicket, 'mpin_setup_pending');
        if (!$claims) {
            throw new \RuntimeException('Invalid or expired pending_ticket. Start again.');
        }

        $this->auth->setMpin($claims['sub'], $mpin);
        $party = $this->partyModel->find($claims['sub']);
        $this->partyModel->update($party['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        (new AuditLogService())->log('auth.mpin_setup_completed', $party['id'], []);

        return [
            'access_token' => $this->issueAccessToken($party),
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TOKEN_TTL_SECONDS,
            'party' => self::toProfile($party),
        ];
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
