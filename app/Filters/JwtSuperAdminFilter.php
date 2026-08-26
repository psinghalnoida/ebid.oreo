<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Libraries\AuthorizationService;
use App\Models\PartyModel;

// JWT counterpart of SuperAdminFilter — for controllers migrated to the
// REST/JWT API. Requires an access token carrying the 'super_admin' role
// claim, which only SuperAdminAuthApiController's separate, TOTP/email-
// OTP-verified login issues (see its class docblock) — holding the DB
// super_admin role alone was never enough, and stays not-enough here.
class JwtSuperAdminFilter implements FilterInterface
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
        if (!$claims || !in_array('super_admin', $claims['roles'] ?? [], true)) {
            return service('response')->setStatusCode(403)->setJSON([
                'error' => 'insufficient_role', 'error_description' => 'This action requires a Super Admin access token.',
            ]);
        }

        // Defense in depth — the role claim is trusted (it's signed), but
        // re-confirm the account still genuinely holds the role, the same
        // way SuperAdminFilter re-checks isSuperAdmin() rather than
        // trusting the session marker alone.
        $party = (new PartyModel())->findActiveById($claims['sub']);
        if (!$party || !(new AuthorizationService())->isSuperAdmin($party['id'])) {
            return service('response')->setStatusCode(403)->setJSON([
                'error' => 'insufficient_role', 'error_description' => 'This action requires Super Admin access.',
            ]);
        }

        UserAuthContext::set($party);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
