<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\OtpVerificationModel;

class AuthService
{
    private const OTP_EXPIRY_MINUTES = 10;
    // Not explicitly specified in BR-02 — a reasonable security default,
    // flagged here rather than silently treated as a settled business rule.
    private const OTP_MAX_ATTEMPTS = 5;
    private const MPIN_FAILURE_LOCKOUT_THRESHOLD = 3; // BR-02: exact figure, confirmed

    private PartyModel $partyModel;
    private OtpVerificationModel $otpModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->otpModel = new OtpVerificationModel();
    }

    // BR-03: 10-digit Indian mobile numbers with +91
    public static function isValidIndianMobile(string $mobileNumber): bool
    {
        return (bool) preg_match('/^\+91[6-9]\d{9}$/', $mobileNumber);
    }

    // Generates and stores an OTP. Returns the PLAIN OTP — this is a
    // development/testing convenience only, since the SMS provider is
    // stubbed (SMS_PROVIDER=stub in .env per the tech-stack open item).
    // In production this return value would instead be handed to the SMS
    // provider and never surfaced to the caller.
    public function requestOtp(string $mobileNumber, string $purpose): string
    {
        if (!self::isValidIndianMobile($mobileNumber)) {
            throw new \RuntimeException('BR-03 violation: invalid Indian mobile number format (expected +91XXXXXXXXXX)');
        }
        if (!in_array($purpose, ['registration', 'mpin_reset'], true)) {
            throw new \RuntimeException("Unknown OTP purpose: {$purpose}");
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::OTP_EXPIRY_MINUTES . ' minutes');

        $this->otpModel->createOtp(
            Uuid::v4(), $mobileNumber, password_hash($otp, PASSWORD_BCRYPT),
            $purpose, $expiresAt->format('Y-m-d H:i:s')
        );

        return $otp;
    }

    // Returns true/false rather than throwing, so the controller can show
    // a clean "incorrect OTP" message without a stack trace.
    public function verifyOtp(string $mobileNumber, string $purpose, string $submittedOtp): bool
    {
        $record = $this->otpModel->findActive($mobileNumber, $purpose);
        if (!$record) {
            return false;
        }
        if (new \DateTimeImmutable() > new \DateTimeImmutable($record['expires_at'])) {
            return false;
        }
        if ((int) $record['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            return false;
        }

        if (!password_verify($submittedOtp, $record['otp_hash'])) {
            $this->otpModel->incrementAttempts($record['id']);
            return false;
        }

        $this->otpModel->markVerified($record['id']);
        return true;
    }

    // BR-02: registration — call only after verifyOtp() succeeded for
    // purpose='registration'. Creates the party if one doesn't already exist.
    public function completeRegistration(string $mobileNumber, string $entityType = 'individual'): array
    {
        $existing = $this->partyModel->findByMobile($mobileNumber);
        if ($existing) {
            return $existing;
        }
        $party = $this->partyModel->createParty($mobileNumber, $entityType);
        $this->partyModel->update($party['id'], ['mobile_verified_at' => date('Y-m-d H:i:s')]);
        return $this->partyModel->find($party['id']);
    }

    // BR-02: 4-digit mPIN, stored as a hash
    public function setMpin(string $partyId, string $mpin): void
    {
        if (!preg_match('/^\d{4}$/', $mpin)) {
            throw new \RuntimeException('mPIN must be exactly 4 digits');
        }
        $this->partyModel->setMpinHash($partyId, password_hash($mpin, PASSWORD_BCRYPT));
        $this->partyModel->resetFailedMpinAttempts($partyId);
    }

    // BR-02: mPIN authentication with 3-consecutive-failure lockout.
    // Returns ['status' => 'ok', 'party' => ...] on success,
    // ['status' => 'invalid_mpin', 'attemptsRemaining' => n] on a bad
    // attempt that hasn't hit the threshold yet, or
    // ['status' => 'otp_required'] once the 3rd consecutive failure lands
    // — per BR-02, an SMS OTP must then be verified before mPIN reset or
    // re-authentication can proceed.
    public function authenticateWithMpin(string $mobileNumber, string $mpin): array
    {
        $party = $this->partyModel->findByMobile($mobileNumber);
        if (!$party || !$party['mpin_hash']) {
            throw new \RuntimeException('No registered party with a set mPIN for this mobile number');
        }

        if (password_verify($mpin, $party['mpin_hash'])) {
            $this->partyModel->resetFailedMpinAttempts($party['id']);
            return ['status' => 'ok', 'party' => $this->partyModel->find($party['id'])];
        }

        $attempts = $this->partyModel->incrementFailedMpinAttempts($party['id']);
        if ($attempts >= self::MPIN_FAILURE_LOCKOUT_THRESHOLD) {
            return ['status' => 'otp_required', 'partyId' => $party['id']];
        }

        return ['status' => 'invalid_mpin', 'attemptsRemaining' => self::MPIN_FAILURE_LOCKOUT_THRESHOLD - $attempts];
    }

    // BR-02: after OTP verification post-lockout, mPIN can be reset.
    public function resetMpinAfterOtp(string $partyId, string $newMpin): void
    {
        $this->setMpin($partyId, $newMpin);
    }
}
