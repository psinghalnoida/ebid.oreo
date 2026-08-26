<?php

namespace App\Libraries;

// Real, self-signed HS256 JWT (RFC 7519) — no vendor library added, same
// substitution pattern already established in this codebase by
// ApiCredentialService (Tenant API bearer tokens) and TotpService (RFC 6238
// TOTP): a genuinely HMAC-signed, standard-shaped token, not a fake
// stand-in. header.payload.signature, all base64url, verified with
// hash_equals to stay constant-time.
class JwtService
{
    // Two independent token types share this codec but never each other's
    // secret, so an "otp verified" ticket can never be replayed as a full
    // access token even if someone forges the "typ" claim by hand.
    public static function encode(array $claims, string $secretEnvVar, string $devFallbackSecret): string
    {
        $header = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode($claims));
        $signingInput = $header . '.' . $payload;
        $signature = self::sign($signingInput, $secretEnvVar, $devFallbackSecret);

        return $signingInput . '.' . $signature;
    }

    // Returns the decoded claims array, or null if the token is malformed,
    // the signature doesn't match, or (when the claims contain "exp") the
    // token has expired.
    public static function decode(string $token, string $secretEnvVar, string $devFallbackSecret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $expectedSignature = self::sign($headerB64 . '.' . $payloadB64, $secretEnvVar, $devFallbackSecret);
        if (!hash_equals($expectedSignature, $signatureB64)) {
            return null;
        }

        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }

        $claims = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($claims)) {
            return null;
        }
        if (isset($claims['exp']) && time() > (int) $claims['exp']) {
            return null;
        }

        return $claims;
    }

    private static function sign(string $signingInput, string $secretEnvVar, string $devFallbackSecret): string
    {
        $secret = getenv($secretEnvVar) ?: $devFallbackSecret;
        return self::base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
