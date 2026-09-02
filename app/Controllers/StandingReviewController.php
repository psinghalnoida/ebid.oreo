<?php

namespace App\Controllers;

use App\Libraries\StandingReviewService;
use App\Libraries\UserAuthContext;
use App\Models\DisputeModel;

// Authorization (Tenant Admin for one of the seller's tenants, or Super
// Admin) is checked inside StandingReviewService itself — same pattern
// as DisputeController::rule — so this runs behind jwtAuth, not a
// role-specific filter.
class StandingReviewController extends BaseController
{
    public function show(string $disputeId)
    {
        $dispute = (new DisputeModel())->find($disputeId);
        if (!$dispute || $dispute['category'] !== 'standing_review') {
            return $this->jsonError(404, 'not_found', 'Standing Review case not found.');
        }

        $db = \Config\Database::connect();
        $seller = $db->table('party')->where('id', $dispute['respondent_party_id'])->get()->getRowArray();
        $tenants = $db->table('seller_application ta')
            ->select('t.id, t.name')
            ->join('tenant t', 't.id = ta.tenant_id')
            ->where('ta.party_id', $dispute['respondent_party_id'])
            ->where('ta.status', 'approved')
            ->get()->getResultArray();

        return $this->apiResponse(['dispute' => $dispute, 'seller' => $seller, 'tenants' => $tenants]);
    }

    public function rule(string $disputeId)
    {
        $partyId = UserAuthContext::partyId();
        $tenantId = $this->input('tenant_id');
        $outcome = $this->input('outcome');
        $rationale = $this->input('rationale');
        $ratingConsequence = $this->input('rating_consequence') !== null && $this->input('rating_consequence') !== '' ? (float) $this->input('rating_consequence') : null;

        try {
            (new StandingReviewService())->ruleOnCase($disputeId, $partyId, $tenantId, $outcome, $rationale, $ratingConsequence);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'rule_failed', $e->getMessage());
        }

        return $this->apiResponse(['dispute' => (new DisputeModel())->find($disputeId), 'message' => 'Standing Review case ruled.']);
    }
}
