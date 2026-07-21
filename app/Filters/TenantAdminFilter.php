<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\AuthorizationService;

// BR-09: only the Tenant Admin whose tenant owns the target listing/sale
// event may approve, reject, or otherwise administer it. Applied via
// route filter argument: 'listing' or 'saleEvent', telling this filter
// which resource type the URI's ID segment refers to.
class TenantAdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }

        $resourceType = $arguments[0] ?? 'listing';
        $segments = $request->getUri()->getSegments();
        // e.g. ['listings', '{id}', 'dev-approve'] or ['sale-events', '{id}', 'dev-approve']
        $resourceId = $segments[1] ?? null;
        if (!$resourceId) {
            return service('response')->setStatusCode(400)->setBody('Missing resource ID');
        }

        $auth = new AuthorizationService();
        $authorized = match ($resourceType) {
            'saleEvent' => $auth->isTenantAdminForSaleEvent($partyId, $resourceId),
            'settlement' => $auth->isTenantAdminForSettlement($partyId, $resourceId),
            default => $auth->isTenantAdminForListing($partyId, $resourceId),
        };

        if (!$authorized) {
            return service('response')->setStatusCode(403)
                ->setBody('BR-09: you are not the Tenant Admin for this listing\'s tenant.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed.
    }
}
