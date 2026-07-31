<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\ApiCredentialService;
use App\Libraries\ApiRequestContext;
use App\Models\TenantModel;

// BR-62/64: authenticates every /api request via the OAuth2
// client-credentials bearer token (ApiCredentialService), hard-scopes it
// to the token's own tenantId, and enforces the baseline BR-66 gate (a
// CoCo Starter tenant has no API access at all, full stop). Endpoint-
// specific tier gating (read-only vs. push) is enforced by the
// controller action itself, since it varies per action.
class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => 'invalid_request', 'error_description' => 'Missing or malformed Authorization: Bearer header.',
            ]);
        }

        $claims = (new ApiCredentialService())->validateToken($matches[1]);
        if (!$claims) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => 'invalid_token', 'error_description' => 'The access token is missing, expired, malformed, or the credential has been revoked.',
            ]);
        }

        $tenant = (new TenantModel())->find($claims['tenantId']);
        if (!$tenant || !TenantModel::hasApiAccess($tenant['subscription_tier'])) {
            return service('response')->setStatusCode(403)->setJSON([
                'error' => 'insufficient_tier', 'error_description' => 'BR-66: this TSX\'s subscription tier does not include API access.',
            ]);
        }

        ApiRequestContext::set($claims['tenantId'], $claims['clientId'], $claims['credentialId']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed.
    }
}
