<?php

namespace App\Controllers;

use App\Libraries\AuthorizationService;
use App\Libraries\PayoutControlService;
use App\Libraries\UserAuthContext;
use App\Models\PayoutReleaseReviewModel;
use App\Models\PartyRoleModel;

// BR-50: "High-value pending payouts additionally require Tenant Admin
// or SaaS Admin review" — deliberately EITHER role, unlike AML (BR-54,
// SaaS Admin only). Runs behind jwtAuth (any authenticated party);
// authorization itself is checked inline since it's an OR of two
// different role checks, not expressible as a single route filter.
class PayoutReviewController extends BaseController
{
    public function index()
    {
        $partyId = UserAuthContext::partyId();
        $reviewModel = new PayoutReleaseReviewModel();
        // Only a token issued through the separate, TOTP/email-OTP-
        // verified Super Admin login counts here — the same boundary
        // SuperAdminFilter's session marker enforced.
        $isSuperAdmin = UserAuthContext::hasRole('super_admin') && (new AuthorizationService())->isSuperAdmin($partyId);

        if ($isSuperAdmin) {
            $pending = $reviewModel->findPending();
            $reviewed = $reviewModel->findReviewed();
        } else {
            $tenantIds = (new PartyRoleModel())->findAdministeredTenantIds($partyId);
            if (empty($tenantIds)) {
                return $this->jsonError(403, 'forbidden', 'BR-50: this requires Tenant Admin or Super Admin access.');
            }
            $pending = $reviewModel->findPendingForTenants($tenantIds);
            $reviewed = $reviewModel->findReviewedForTenants($tenantIds);
        }

        return $this->response->setJSON(['pending' => $pending, 'reviewed' => $reviewed]);
    }

    public function decide(string $reviewId)
    {
        $partyId = UserAuthContext::partyId();
        $reviewModel = new PayoutReleaseReviewModel();
        $review = $reviewModel->find($reviewId);
        if (!$review) {
            return $this->jsonError(404, 'not_found', 'Review not found.');
        }

        $hold = (new \App\Models\EmdHoldModel())->find($review['emd_hold_id']);
        $saleEventId = $hold['sale_event_id'];
        $authz = new AuthorizationService();
        $isSuperAdmin = UserAuthContext::hasRole('super_admin') && $authz->isSuperAdmin($partyId);
        if (!$authz->isTenantAdminForSaleEvent($partyId, $saleEventId) && !$isSuperAdmin) {
            return $this->jsonError(403, 'forbidden', 'BR-50: this review requires the sale event\'s Tenant Admin, or Super Admin.');
        }

        $approve = $this->input('decision') === 'approve';
        $rationale = $this->input('rationale');

        try {
            (new PayoutControlService())->decideReview($reviewId, $partyId, $approve, $rationale);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'decide_failed', $e->getMessage());
        }

        return $this->response->setJSON(['review' => $reviewModel->find($reviewId)]);
    }
}
