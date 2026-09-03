<?php

namespace App\Controllers;

use App\Libraries\RatingService;
use App\Libraries\UserAuthContext;
use App\Models\PartyModel;

// jwtSuperAdmin-gated — pulled forward from Phase 5 since it's a single
// small action, same migration as the rest of this phase (D-134).
class SellerDelistingController extends BaseController
{
    public function submit()
    {
        $superAdminId = UserAuthContext::partyId();
        $mobile = $this->input('mobile_number');
        $reason = $this->input('confirmed_fraud_reason');

        if (!$mobile || !$reason) {
            return $this->jsonError(422, 'missing_fields', 'Both the seller\'s mobile number and a confirmed-fraud reason are required.');
        }

        $party = (new PartyModel())->findByMobile($mobile);
        if (!$party) {
            return $this->jsonError(404, 'not_found', 'No registered party found with that mobile number.');
        }

        try {
            $result = (new RatingService())->delistSellerForFraud($party['id'], $superAdminId, $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'delist_failed', $e->getMessage());
        }

        return $this->apiResponse([
            'listingsSuspended' => $result['listingsSuspended'],
            'message' => "Seller delisted. {$result['listingsSuspended']} active listing(s) suspended across every tenant.",
        ]);
    }
}
