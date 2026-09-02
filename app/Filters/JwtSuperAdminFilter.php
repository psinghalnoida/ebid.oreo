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
    // TEMPORARY testing bypass, project owner's explicit request: the
    // Custodian login method is being replaced and nobody can currently
    // get into the admin area to test it. Gated behind an env flag that
    // defaults OFF — env('admin.authBypass') must be explicitly set to
    // 'true' in .env for this to do anything, so a deploy that doesn't
    // touch that key stays exactly as locked down as before. When on,
    // EVERY request through this filter is treated as the first party
    // holding the super_admin role, with no token at all.
    //
    // DELETE THIS BLOCK (and the matching one in
    // SuperAdminAuthApiController::devBypassLogin()) once the real
    // Custodian login method is implemented — do not ship a production
    // deploy with admin.authBypass=true.
    private function isBypassEnabled(): bool
    {
        return env('admin.authBypass', false) === true || env('admin.authBypass') === 'true';
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($this->isBypassEnabled()) {
            $party = (new AuthorizationService())->firstSuperAdminParty();
            if (!$party) {
                return \App\Libraries\ApiResponse::send(service('response'), [
                    'error' => 'no_super_admin', 'error_description' => 'admin.authBypass is on but no party holds the super_admin role yet — run php spark bootstrap:custodian first.',
                ], null, 500);
            }
            UserAuthContext::set($party, ['party', 'super_admin']);
            return;
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'invalid_request', 'error_description' => 'Missing or malformed Authorization: Bearer header.',
            ], null, 401);
        }

        $claims = UserAuthApiService::validateAccessToken($matches[1]);
        if (!$claims || !in_array('super_admin', $claims['roles'] ?? [], true)) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'insufficient_role', 'error_description' => 'This action requires a Super Admin access token.',
            ], null, 403);
        }

        // Defense in depth — the role claim is trusted (it's signed), but
        // re-confirm the account still genuinely holds the role, the same
        // way SuperAdminFilter re-checks isSuperAdmin() rather than
        // trusting the session marker alone.
        $party = (new PartyModel())->findActiveById($claims['sub']);
        if (!$party || !(new AuthorizationService())->isSuperAdmin($party['id'])) {
            return \App\Libraries\ApiResponse::send(service('response'), [
                'error' => 'insufficient_role', 'error_description' => 'This action requires Super Admin access.',
            ], null, 403);
        }

        UserAuthContext::set($party, $claims['roles']);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
