<?php

namespace App\Controllers;

use App\Libraries\ChargebackService;
use App\Libraries\UserAuthContext;
use App\Models\ChargebackCaseModel;
use App\Models\EmdHoldModel;

class ChargebackController extends BaseController
{
    // ⚠️ DEV-ONLY: simulates an incoming chargeback notice. The real flow
    // (BR-52/PR-30 step 191) is triggered by a payment gateway webhook —
    // not yet integrated, same accepted external dependency as
    // BidController::devFundEmd. Filed by the buyer against their own
    // held/forfeited EMD deposit, standing in for the card network's
    // notice the gateway would otherwise deliver. Migrated to JWT
    // (jwtAuth route filter) — D-133.
    public function devFile(string $saleEventId)
    {
        $partyId = UserAuthContext::partyId();

        $hold = (new EmdHoldModel())->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$hold) {
            return $this->jsonError(422, 'no_emd_hold', 'You have no EMD deposit on this sale event to dispute.');
        }

        $reason = trim((string) $this->input('reason')) ?: 'Buyer-initiated chargeback (dev simulation)';

        try {
            $case = (new ChargebackService())->fileChargeback($hold['id'], $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'file_failed', $e->getMessage());
        }

        return $this->apiResponse([
            'case' => $case,
            'message' => 'Chargeback filed. The evidence package has been assembled automatically.',
        ], null, 201);
    }

    // ── Below: admin review screens, jwtSuperAdmin-gated (Phase 5, D-135) ──
    public function index()
    {
        $caseModel = new ChargebackCaseModel();
        return $this->apiResponse([
            'openRepresentment' => $caseModel->findOpenRepresentment(),
            'pendingIntegrityReview' => $caseModel->findPendingIntegrityReview(),
            'resolved' => $caseModel->findResolved(),
        ]);
    }

    public function decide(string $caseId)
    {
        $adminId = UserAuthContext::partyId();
        $outcome = $this->input('outcome');
        $notes = (string) $this->input('notes');

        try {
            (new ChargebackService())->recordRepresentmentOutcome($caseId, $adminId, $outcome, $notes);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'decide_failed', $e->getMessage());
        }

        return $this->apiResponse(['message' => 'Representment outcome recorded.']);
    }

    public function reviewIntegrity(string $caseId)
    {
        $adminId = UserAuthContext::partyId();
        $applyRatingConsequence = (string) $this->input('apply_rating_consequence') === '1';
        $notes = (string) $this->input('notes');

        try {
            (new ChargebackService())->reviewIntegrityFlag($caseId, $adminId, $applyRatingConsequence, $notes);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'review_failed', $e->getMessage());
        }

        return $this->apiResponse(['message' => 'Chargeback integrity review recorded.']);
    }
}
