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
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }

        $auth = new AuthorizationService();
        if (!$auth->isSuperAdmin($partyId)) {
            return service('response')->setStatusCode(403)->setBody('This action requires Super Admin access.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
