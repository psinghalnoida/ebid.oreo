<?php

namespace App\Libraries;

/**
 * Real SMS delivery via 2Factor.in's "SMS with your own OTP" API
 * (https://2factor.in/API/V1/{api_key}/SMS/{mobile}/{otp}/{template}).
 * Counterpart of EmailNotificationService for the mobile-OTP side --
 * every OTP purpose (registration, login, mPIN reset, payout-bank
 * change, Custodian forgot-password, ...) goes through
 * AuthService::requestOtp()/requestOtp-adjacent callers, which call
 * this centrally, so wiring it here covers all of them in one place.
 *
 * The API key defaults to the project owner's own 2Factor.in key
 * (explicitly provided for this integration) but is overridable via
 * SMS.twoFactorApiKey / TWO_FACTOR_API_KEY in .env for other
 * environments/deployments.
 *
 * Fails closed (returns false, logs a warning) on any transport error
 * or non-"Success" API response -- callers must keep their existing
 * on-screen dev_otp fallback for local/dev use; this never throws.
 */
class SmsNotificationService
{
    // Project owner's explicit key for this integration (see the task
    // that wired this class up). Overridable via env for other deploys.
    private const DEFAULT_API_KEY = 'e05e2067-06ec-11eb-9fa5-0200cd936042';
    private const DEFAULT_TEMPLATE = 'Claimsmitra Phone Verification';
    private const BASE_URL = 'https://2factor.in/API/V1';

    public function sendOtp(string $mobileNumber, string $otp, string $purpose = 'otp'): bool
    {
        $apiKey = env('SMS.twoFactorApiKey', self::DEFAULT_API_KEY) ?: self::DEFAULT_API_KEY;
        $template = env('SMS.twoFactorTemplate', self::DEFAULT_TEMPLATE) ?: self::DEFAULT_TEMPLATE;

        // 2Factor expects a bare 10-digit Indian mobile number (no '+91').
        $mobile = preg_replace('/^\+?91/', '', $mobileNumber);
        $mobile = preg_replace('/\D/', '', (string) $mobile);
        if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            log_message('warning', "SmsNotificationService: refusing to send for {$purpose} -- '{$mobileNumber}' is not a valid 10-digit Indian mobile number.");
            return false;
        }

        $url = self::BASE_URL . '/' . $apiKey . '/SMS/' . $mobile . '/' . $otp . '/' . rawurlencode($template);

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $error = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false) {
                log_message('warning', "SmsNotificationService: transport error sending {$purpose} OTP to {$mobile}: {$error}");
                return false;
            }

            $decoded = json_decode($body, true);
            $ok = $status === 200 && is_array($decoded) && ($decoded['Status'] ?? null) === 'Success';
            if (!$ok) {
                log_message('warning', "SmsNotificationService: 2Factor.in returned failure for {$purpose} OTP to {$mobile} (HTTP {$status}): {$body}");
            }
            return $ok;
        } catch (\Throwable $e) {
            log_message('warning', "SmsNotificationService: exception sending {$purpose} OTP to {$mobile}: {$e->getMessage()}");
            return false;
        }
    }
}
