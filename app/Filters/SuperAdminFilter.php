<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\AuthorizationService;

// ⚠️ Same minimal-stand-in caveat as GrantSuperAdmin — role check only,
// not BR-04's real Super Admin auth path.
class SuperAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // BR-04: requires having gone through the SEPARATE, TOTP-verified
        // Super Admin login (SuperAdminAuthController::loginSubmit), not
        // just holding the super_admin role while logged in via the
        // regular mobile/OTP/mPIN flow. This is what makes "separate
        // login path" a real security boundary rather than a role check
        // that any session could satisfy.
        $verifiedAt = session()->get('super_admin_totp_verified_at');
        if (!$verifiedAt) {
            return redirect()->to('/admin/login');
        }

        $partyId = session()->get('super_admin_party_id');
        $auth = new AuthorizationService();
        if (!$partyId || !$auth->isSuperAdmin($partyId)) {
            return service('response')->setStatusCode(403)->setBody('This action requires Super Admin access.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
