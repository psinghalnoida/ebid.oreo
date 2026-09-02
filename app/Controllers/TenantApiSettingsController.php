<?php

namespace App\Controllers;

use App\Libraries\ApiCredentialService;
use App\Libraries\UserAuthContext;
use App\Models\TenantApiCredentialModel;
use App\Models\TenantModel;

// BR-62-66: Tenant Admin-facing credential issuance/revocation and
// webhook URL registration. Access enforced by the jwtTenantAdmin route
// filter, not by this controller.
class TenantApiSettingsController extends BaseController
{
    public function index(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        return $this->apiResponse([
            'tenant' => $tenant,
            'hasApiAccess' => TenantModel::hasApiAccess($tenant['subscription_tier']),
            'canPushListings' => TenantModel::canPushListings($tenant['subscription_tier']),
            'canPushSaleEvents' => TenantModel::canPushSaleEvents($tenant['subscription_tier']),
            'credentials' => (new TenantApiCredentialModel())->findForTenant($tenantId),
        ]);
    }

    // BR-62: "credentials are issued at the Tenant level... established
    // as part of the same formal agreement that establishes the Tenant
    // Admin." The plaintext secret is returned exactly once, in this
    // response, and never retrievable again.
    public function issueCredential(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }
        if (!TenantModel::hasApiAccess($tenant['subscription_tier'])) {
            return $this->jsonError(403, 'no_api_access', 'BR-66: this TSX\'s subscription tier (CoCo Starter) has no API access.');
        }

        $issued = (new ApiCredentialService())->issueCredential($tenantId, UserAuthContext::partyId());

        return $this->apiResponse([
            'clientId' => $issued['credential']['client_id'], 'clientSecret' => $issued['clientSecret'],
        ], null, 201);
    }

    public function revokeCredential(string $tenantId, string $credentialId)
    {
        $credential = (new TenantApiCredentialModel())->find($credentialId);
        if (!$credential || $credential['tenant_id'] !== $tenantId) {
            return $this->jsonError(404, 'not_found', 'Credential not found.');
        }
        (new ApiCredentialService())->revokeCredential($credentialId, UserAuthContext::partyId());
        return $this->apiResponse(['message' => 'Credential revoked — any outstanding access token is rejected immediately.']);
    }

    public function updateWebhookUrl(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        $url = trim((string) $this->input('webhook_url'));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->jsonError(422, 'invalid_url', 'Webhook URL must be a valid URL, or left blank to disable webhook delivery.');
        }

        $update = ['webhook_url' => $url ?: null];
        // Generated once, the first time a webhook_url is set — reused
        // for every delivery signature after that.
        if ($url !== '' && empty($tenant['webhook_signing_secret'])) {
            $update['webhook_signing_secret'] = bin2hex(random_bytes(32));
        }

        (new TenantModel())->update($tenantId, $update);
        return $this->apiResponse(['message' => $url ? 'Webhook URL saved.' : 'Webhook URL cleared — webhook delivery disabled.']);
    }
}
