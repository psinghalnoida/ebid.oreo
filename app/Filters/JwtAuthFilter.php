<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Models\PartyModel;

// Authenticates REST/JWT endpoints (UserAuthController and anything else
// under /api/v1/me/*) via the access token issued by
// UserAuthApiService::completeLogin(). Mirrors ApiAuthFilter's shape
// (Tenant API bearer auth) but validates a user-scoped JWT instead of a
// Tenant API credential token, and sets the authenticated party on the
// request rather than a tenant/client context.
class JwtAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => 'invalid_request', 'error_description' => 'Missing or malformed Authorization: Bearer header.',
            ]);
        }

        $claims = UserAuthApiService::validateAccessToken($matches[1]);
        if (!$claims) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => 'invalid_token', 'error_description' => 'The access token is missing, expired, or malformed.',
            ]);
        }

        $party = (new PartyModel())->findActiveById($claims['sub']);
        if (!$party) {
            return service('response')->setStatusCode(401)->setJSON([
                'error' => 'invalid_token', 'error_description' => 'The account for this token no longer exists.',
            ]);
        }

        UserAuthContext::set($party, $claims['roles'] ?? ['party']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed.
    }
}
