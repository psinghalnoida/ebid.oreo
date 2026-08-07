<?php

namespace App\Controllers;

use App\Libraries\AuthorizationService;
use App\Libraries\RatingService;
use App\Models\RatingEventModel;
use App\Models\PartyRoleModel;

// BR-36: a genuine review queue for pending rating downgrades — closes
// a gap that predates this decision (SettlementService's "problem"
// rating and StandingReviewService's CBS/escalation consequences have
// always created pending downgrades with no real HTTP way for a Tenant
// Admin or Super Admin to ever approve them). Same dual-authorization
// shape as PayoutReviewController (BR-50): either role may act, scoped
// to the tenants a Tenant Admin actually administers.
class RatingReviewController extends BaseController
{
    public function index()
    {
        $partyId = $this->currentReviewerId();
        if (!$partyId) return redirect()->to('/login');

        $eventModel = new RatingEventModel();
        $isSuperAdmin = session()->get('super_admin_totp_verified_at') && (new AuthorizationService())->isSuperAdmin($partyId);

        if ($isSuperAdmin) {
            // Super Admin sees everything, including events with no
            // related_sale_event_id (e.g. Standing Review's system-
            // initiated escalation consequence — no tenant to scope a
            // Tenant Admin to).
            $pending = $eventModel->findAllPending();
        } else {
            $tenantIds = (new PartyRoleModel())->findAdministeredTenantIds($partyId);
            if (empty($tenantIds)) {
                return service('response')->setStatusCode(403)->setBody('BR-36: this requires Tenant Admin or Super Admin access.');
            }
            $pending = $eventModel->findPendingForTenants($tenantIds);
        }

        return view('admin/rating_reviews', ['title' => 'Pending Rating Downgrades — AdwitiX', 'pending' => $pending]);
    }

    public function approve(string $eventId)
    {
        $partyId = $this->currentReviewerId();
        if (!$partyId) return redirect()->to('/login');

        $eventModel = new RatingEventModel();
        $event = $eventModel->find($eventId);
        if (!$event) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $authz = new AuthorizationService();
        $isSuperAdmin = session()->get('super_admin_totp_verified_at') && $authz->isSuperAdmin($partyId);
        $isTenantAdmin = $event['related_sale_event_id'] && $authz->isTenantAdminForSaleEvent($partyId, $event['related_sale_event_id']);

        if (!$isSuperAdmin && !$isTenantAdmin) {
            return service('response')->setStatusCode(403)->setBody('BR-36: this requires the sale event\'s Tenant Admin, or Super Admin.');
        }

        try {
            (new RatingService())->approveDowngrade($eventId, $partyId, $isSuperAdmin ? 'super_admin' : 'tenant_admin');
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/rating-reviews')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/rating-reviews');
    }

    private function currentReviewerId(): ?string
    {
        $superAdminId = session()->get('super_admin_totp_verified_at') ? session()->get('super_admin_party_id') : null;
        return $superAdminId ?? session()->get('logged_in_party_id');
    }
}
