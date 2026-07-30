<?php

namespace App\Controllers;

use App\Libraries\ApiCredentialService;
use App\Models\TenantApiCredentialModel;
use App\Models\TenantModel;

// BR-62-66: Tenant Admin-facing credential issuance/revocation and
// webhook URL registration. Access enforced by the tenantAdmin route
// filter, not by this controller.
class TenantApiSettingsController extends BaseController
{
    public function index(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('tenant_admin/api_access', [
            'title' => 'API Access — ' . $tenant['name'],
            'tenant' => $tenant,
            'hasApiAccess' => TenantModel::hasApiAccess($tenant['subscription_tier']),
            'canPushListings' => TenantModel::canPushListings($tenant['subscription_tier']),
            'canPushSaleEvents' => TenantModel::canPushSaleEvents($tenant['subscription_tier']),
            'credentials' => (new TenantApiCredentialModel())->findForTenant($tenantId),
        ]);
    }

    // BR-62: "credentials are issued at the Tenant level... established
    // as part of the same formal agreement that establishes the Tenant
    // Admin." The plaintext secret is shown exactly once, on this
    // redirect's flash message, and never retrievable again.
    public function issueCredential(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (!TenantModel::hasApiAccess($tenant['subscription_tier'])) {
            return redirect()->to("/tenants/{$tenantId}/api-access")->with('error', 'BR-66: this TSX\'s subscription tier (CoCo Starter) has no API access.');
        }

        $issued = (new ApiCredentialService())->issueCredential($tenantId, session()->get('logged_in_party_id'));

        return redirect()->to("/tenants/{$tenantId}/api-access")->with('newCredential', [
            'clientId' => $issued['credential']['client_id'], 'clientSecret' => $issued['clientSecret'],
        ]);
    }

    public function revokeCredential(string $tenantId, string $credentialId)
    {
        $credential = (new TenantApiCredentialModel())->find($credentialId);
        if (!$credential || $credential['tenant_id'] !== $tenantId) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        (new ApiCredentialService())->revokeCredential($credentialId, session()->get('logged_in_party_id'));
        return redirect()->to("/tenants/{$tenantId}/api-access")->with('error', 'Credential revoked — any outstanding access token is rejected immediately.');
    }

    public function updateWebhookUrl(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $url = trim((string) $this->request->getPost('webhook_url'));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect()->to("/tenants/{$tenantId}/api-access")->with('error', 'Webhook URL must be a valid URL, or left blank to disable webhook delivery.');
        }

        $update = ['webhook_url' => $url ?: null];
        // Generated once, the first time a webhook_url is set — reused
        // for every delivery signature after that.
        if ($url !== '' && empty($tenant['webhook_signing_secret'])) {
            $update['webhook_signing_secret'] = bin2hex(random_bytes(32));
        }

        (new TenantModel())->update($tenantId, $update);
        return redirect()->to("/tenants/{$tenantId}/api-access")->with('error', $url ? 'Webhook URL saved.' : 'Webhook URL cleared — webhook delivery disabled.');
    }
}
