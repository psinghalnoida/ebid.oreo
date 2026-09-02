<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Libraries\AuthorizationService;
use App\Models\PartyModel;

// JWT counterpart of TenantAdminFilter — same BR-09 per-resource check
// (only the Tenant Admin whose tenant owns the target resource may act on
// it), just reading the acting party from the Bearer JWT instead of the
// session. Takes the same filter argument ('listing', 'saleEvent',
// 'settlement', 'sellerApplication', 'tenant') as the original.
class JwtTenantAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'invalid_request', 'error_description' => 'Missing or malformed Authorization: Bearer header.',
            ], null, 401);
        }

        $claims = UserAuthApiService::validateAccessToken($matches[1]);
        if (!$claims) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'invalid_token', 'error_description' => 'The access token is missing, expired, or malformed.',
            ], null, 401);
        }

        $party = (new PartyModel())->findActiveById($claims['sub']);
        if (!$party) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'invalid_token', 'error_description' => 'The account for this token no longer exists.',
            ], null, 401);
        }

        $resourceType = $arguments[0] ?? 'listing';
        $segments = $request->getUri()->getSegments();
        // e.g. ['api', 'v1', 'listings', '{id}', 'approve'] — the 'api/v1'
        // prefix shifts the resource ID two slots later than the original
        // session-based TenantAdminFilter's un-prefixed routes had it.
        $resourceId = $segments[3] ?? null;
        if (!$resourceId) {
            return \App\Libraries\ApiResponse::send(service('response'), ['error' => 'invalid_request', 'error_description' => 'Missing resource ID'], null, 400);
        }

        $auth = new AuthorizationService();
        $authorized = match ($resourceType) {
            'saleEvent' => $auth->isTenantAdminForSaleEvent($party['id'], $resourceId),
            'settlement' => $auth->isTenantAdminForSettlement($party['id'], $resourceId),
            'sellerApplication' => $auth->isTenantAdminForSellerApplication($party['id'], $resourceId),
            'tenant' => $auth->isTenantAdminFor($party['id'], $resourceId),
            default => $auth->isTenantAdminForListing($party['id'], $resourceId),
        };

        if (!$authorized) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'forbidden', 'error_description' => "You are not the Tenant Admin for this {$resourceType}'s tenant.",
            ], null, 403);
        }

        UserAuthContext::set($party, $claims['roles'] ?? ['party']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
