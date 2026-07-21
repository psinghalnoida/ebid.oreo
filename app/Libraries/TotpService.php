<?php

namespace App\Libraries;

// ⚠️ SUBSTITUTION, FLAGGED EXPLICITLY: BR-04 names "Auth0/TOTP" as the
// Super Admin login mechanism. Auth0 is a paid external vendor requiring
// its own account setup — the same category of dependency as the payment
// gateway and SMS provider, both explicitly deferred (D-23). TOTP itself,
// however, is an open standard (RFC 6238) that doesn't require any
// external vendor — this implements real TOTP, compatible with Google
// Authenticator/Authy/etc., giving Super Admin genuine 2FA without
// needing an Auth0 account. If Auth0 specifically is later required (SSO,
// centralized user management, etc.), this TOTP layer can sit alongside
// or be replaced by it — this is not a fake stand-in, the 2FA it provides
// is real and cryptographically sound.
class TotpService
{
    private const SECRET_LENGTH = 20; // bytes, 160-bit — standard for TOTP
    private const TIME_STEP = 30;      // seconds, standard interval
    private const CODE_DIGITS = 6;
    private const WINDOW = 1;          // allow ±1 step for clock drift

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_LENGTH));
    }

    public static function getProvisioningUri(string $secret, string $accountLabel, string $issuer = 'eBid Hub'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=%d&period=%d',
            rawurlencode($issuer), rawurlencode($accountLabel), $secret,
            rawurlencode($issuer), self::CODE_DIGITS, self::TIME_STEP
        );
    }

    public static function verifyCode(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $currentStep = (int) floor(time() / self::TIME_STEP);
        for ($offset = -self::WINDOW; $offset <= self::WINDOW; $offset++) {
            if (self::generateCode($secret, $currentStep + $offset) === $code) {
                return true;
            }
        }
        return false;
    }

    private static function generateCode(string $secret, int $timeStep): string
    {
        $key = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeStep); // 8-byte big-endian counter
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($truncated % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $byte) {
            $binary .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= $alphabet[bindec($chunk)];
        }
        return $encoded;
    }

    private static function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split(strtoupper($encoded)) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) continue;
            $bytes .= chr(bindec($chunk));
        }
        return $bytes;
    }
}
