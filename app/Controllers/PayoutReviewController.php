<?php

namespace App\Controllers;

use App\Libraries\AuthorizationService;
use App\Libraries\PayoutControlService;
use App\Models\PayoutReleaseReviewModel;
use App\Models\PartyRoleModel;

// BR-50: "High-value pending payouts additionally require Tenant Admin
// or SaaS Admin review" — deliberately EITHER role, unlike AML (BR-54,
// SaaS Admin only). Authorization is checked inline rather than via a
// route filter, since no existing filter expresses "this OR that role",
// only a single required role per route.
class PayoutReviewController extends BaseController
{
    public function index()
    {
        $partyId = $this->currentReviewerId();
        if (!$partyId) return redirect()->to('/login');

        $reviewModel = new PayoutReleaseReviewModel();
        $isSuperAdmin = session()->get('super_admin_totp_verified_at') && (new AuthorizationService())->isSuperAdmin($partyId);

        if ($isSuperAdmin) {
            $pending = $reviewModel->findPending();
            $reviewed = $reviewModel->findReviewed();
        } else {
            $tenantIds = (new PartyRoleModel())->findAdministeredTenantIds($partyId);
            if (empty($tenantIds)) {
                return service('response')->setStatusCode(403)->setBody('BR-50: this requires Tenant Admin or Super Admin access.');
            }
            $pending = $reviewModel->findPendingForTenants($tenantIds);
            $reviewed = $reviewModel->findReviewedForTenants($tenantIds);
        }

        return view('admin/payout_reviews', [
            'title' => 'Payout Release Reviews — eBid Hub',
            'pending' => $pending,
            'reviewed' => $reviewed,
        ]);
    }

    public function decide(string $reviewId)
    {
        $partyId = $this->currentReviewerId();
        if (!$partyId) return redirect()->to('/login');

        $reviewModel = new PayoutReleaseReviewModel();
        $review = $reviewModel->find($reviewId);
        if (!$review) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $hold = (new \App\Models\EmdHoldModel())->find($review['emd_hold_id']);
        $saleEventId = $hold['sale_event_id'];
        $authz = new AuthorizationService();
        if (!$authz->isTenantAdminForSaleEvent($partyId, $saleEventId) && !$authz->isSuperAdmin($partyId)) {
            return service('response')->setStatusCode(403)->setBody('BR-50: this review requires the sale event\'s Tenant Admin, or Super Admin.');
        }

        $approve = $this->request->getPost('decision') === 'approve';
        $rationale = $this->request->getPost('rationale');

        try {
            (new PayoutControlService())->decideReview($reviewId, $partyId, $approve, $rationale);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/payout-reviews')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/payout-reviews');
    }

    // Tenant Admin acts via the regular session; Super Admin only counts
    // if verified through the separate TOTP login path (BR-04) — the
    // same distinction SuperAdminFilter enforces elsewhere.
    private function currentReviewerId(): ?string
    {
        $superAdminId = session()->get('super_admin_totp_verified_at') ? session()->get('super_admin_party_id') : null;
        return $superAdminId ?? session()->get('logged_in_party_id');
    }
}
