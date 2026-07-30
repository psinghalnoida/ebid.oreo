<?php

namespace App\Libraries;

use App\Models\TenantApiCredentialModel;

// ⚠️ SUBSTITUTION, FLAGGED EXPLICITLY: BR-64 names OAuth2 client-credentials
// "through the platform's existing Auth0 relationship." Auth0 is a paid
// external vendor requiring its own account setup — the same category of
// dependency as the payment gateway/SMS provider, both explicitly deferred
// (D-23). This implements a real, self-hosted OAuth2 client-credentials
// flow instead: genuine random client_id/client_secret issuance, bcrypt
// secret hashing, and a genuinely HMAC-signed, short-lived bearer token —
// the same substitution pattern TotpService already established for BR-04's
// Auth0/TOTP requirement (real RFC 6238 TOTP, no vendor needed). Not a fake
// stand-in: a leaked/guessed token is exactly as hard to forge as a real
// signed OAuth2 access token, and BR-64's hard tenant-scoping-at-issuance
// requirement is satisfied for real (a token's tenantId claim is what the
// signature covers, and every request re-checks the credential is still
// active — an instantly-effective revocation, not just policy-enforced).
class ApiCredentialService
{
    // BR-64: "Access tokens are short-lived." Not otherwise quantified —
    // a reasonable default, flagged the same way this codebase's other
    // unquantified thresholds are (OTP-attempt limit, settlement-stall
    // window), not treated as a settled business rule.
    private const TOKEN_TTL_SECONDS = 900; // 15 minutes

    private TenantApiCredentialModel $credentialModel;

    public function __construct()
    {
        $this->credentialModel = new TenantApiCredentialModel();
    }

    // BR-62: "credentials are issued at the Tenant level ... established
    // as part of the same formal agreement that establishes the Tenant
    // Admin." Issued by a Tenant Admin (or Super Admin) via the portal —
    // the plaintext secret is returned ONCE and never retrievable again,
    // standard OAuth2 client-credentials practice.
    public function issueCredential(string $tenantId, ?string $createdByPartyId): array
    {
        $clientId = 'tsx_' . bin2hex(random_bytes(12));
        $clientSecret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $secretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $credential = $this->credentialModel->createCredential($tenantId, $clientId, $secretHash, $createdByPartyId);

        (new AuditLogService())->log('tenant_api_credential.issued', $createdByPartyId, [
            'tenantId' => $tenantId, 'credentialId' => $credential['id'], 'clientId' => $clientId,
        ]);

        return ['credential' => $credential, 'clientSecret' => $clientSecret];
    }

    public function revokeCredential(string $credentialId, ?string $actorPartyId): array
    {
        $credential = $this->credentialModel->find($credentialId);
        if (!$credential) {
            throw new \RuntimeException('API credential not found.');
        }
        $result = $this->credentialModel->revoke($credentialId);
        (new AuditLogService())->log('tenant_api_credential.revoked', $actorPartyId, [
            'tenantId' => $credential['tenant_id'], 'credentialId' => $credentialId,
        ]);
        return $result;
    }

    // OAuth2 client_credentials grant: exchange client_id/client_secret
    // for a short-lived bearer access token.
    public function authenticate(string $clientId, string $clientSecret): array
    {
        $credential = $this->credentialModel->findActiveByClientId($clientId);
        if (!$credential || !password_verify($clientSecret, $credential['client_secret_hash'])) {
            throw new \RuntimeException('invalid_client: unknown client_id or incorrect client_secret.');
        }

        $this->credentialModel->touchLastUsed($credential['id']);

        $expiresAt = time() + self::TOKEN_TTL_SECONDS;
        $token = $this->issueToken($credential['id'], $credential['tenant_id'], $clientId, $expiresAt);

        return ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => self::TOKEN_TTL_SECONDS];
    }

    // Validates a bearer token's signature and expiry, then re-confirms
    // the underlying credential is still active — a revoked credential's
    // outstanding tokens stop working immediately, not just at next
    // refresh. Returns the claims (tenantId hard-scoped at issuance,
    // BR-64) or null if invalid for any reason.
    public function validateToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payloadB64, $signatureB64] = $parts;
        $expectedSignature = $this->sign($payloadB64);
        if (!hash_equals($expectedSignature, $signatureB64)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload) || !isset($payload['cid'], $payload['tid'], $payload['exp'])) {
            return null;
        }
        if (time() > (int) $payload['exp']) {
            return null;
        }

        $credential = $this->credentialModel->find($payload['cid']);
        if (!$credential || $credential['status'] !== 'active' || $credential['tenant_id'] !== $payload['tid']) {
            return null;
        }

        return ['tenantId' => $payload['tid'], 'clientId' => $credential['client_id'], 'credentialId' => $credential['id']];
    }

    private function issueToken(string $credentialId, string $tenantId, string $clientId, int $expiresAt): string
    {
        $payload = self::base64UrlEncode(json_encode([
            'cid' => $credentialId, 'tid' => $tenantId, 'client_id' => $clientId, 'exp' => $expiresAt,
        ]));
        return $payload . '.' . $this->sign($payload);
    }

    private function sign(string $payloadB64): string
    {
        $secret = getenv('EBIDHUB_API_TOKEN_SECRET') ?: 'dev-only-change-in-production';
        return self::base64UrlEncode(hash_hmac('sha256', $payloadB64, $secret, true));
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
