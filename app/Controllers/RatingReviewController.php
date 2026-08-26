<?php

namespace App\Controllers;

use App\Libraries\AuthorizationService;
use App\Libraries\RatingService;
use App\Libraries\UserAuthContext;
use App\Models\RatingEventModel;
use App\Models\PartyRoleModel;

// BR-36: a genuine review queue for pending rating downgrades. Same
// dual-authorization shape as PayoutReviewController (BR-50): either
// role may act, scoped to the tenants a Tenant Admin actually
// administers. Runs behind jwtAuth; authorization checked inline.
class RatingReviewController extends BaseController
{
    public function index()
    {
        $partyId = UserAuthContext::partyId();
        $eventModel = new RatingEventModel();
        $isSuperAdmin = UserAuthContext::hasRole('super_admin') && (new AuthorizationService())->isSuperAdmin($partyId);

        if ($isSuperAdmin) {
            // Super Admin sees everything, including events with no
            // related_sale_event_id (e.g. Standing Review's system-
            // initiated escalation consequence — no tenant to scope a
            // Tenant Admin to).
            $pending = $eventModel->findAllPending();
        } else {
            $tenantIds = (new PartyRoleModel())->findAdministeredTenantIds($partyId);
            if (empty($tenantIds)) {
                return $this->jsonError(403, 'forbidden', 'BR-36: this requires Tenant Admin or Super Admin access.');
            }
            $pending = $eventModel->findPendingForTenants($tenantIds);
        }

        return $this->response->setJSON(['pending' => $pending]);
    }

    public function approve(string $eventId)
    {
        $partyId = UserAuthContext::partyId();

        $eventModel = new RatingEventModel();
        $event = $eventModel->find($eventId);
        if (!$event) {
            return $this->jsonError(404, 'not_found', 'Rating event not found.');
        }

        $authz = new AuthorizationService();
        $isSuperAdmin = UserAuthContext::hasRole('super_admin') && $authz->isSuperAdmin($partyId);
        $isTenantAdmin = $event['related_sale_event_id'] && $authz->isTenantAdminForSaleEvent($partyId, $event['related_sale_event_id']);

        if (!$isSuperAdmin && !$isTenantAdmin) {
            return $this->jsonError(403, 'forbidden', 'BR-36: this requires the sale event\'s Tenant Admin, or Super Admin.');
        }

        try {
            (new RatingService())->approveDowngrade($eventId, $partyId, $isSuperAdmin ? 'super_admin' : 'tenant_admin');
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'approve_failed', $e->getMessage());
        }

        return $this->response->setJSON(['event' => $eventModel->find($eventId)]);
    }
}
