<?php

namespace App\Controllers;

use App\Libraries\ChargebackService;
use App\Models\ChargebackCaseModel;
use App\Models\EmdHoldModel;

class ChargebackController extends BaseController
{
    // ⚠️ DEV-ONLY: simulates an incoming chargeback notice. The real flow
    // (BR-52/PR-30 step 191) is triggered by a payment gateway webhook —
    // not yet integrated, same accepted external dependency as
    // BidController::devFundEmd. Filed by the buyer against their own
    // held/forfeited EMD deposit, standing in for the card network's
    // notice the gateway would otherwise deliver.
    public function devFile(string $saleEventId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return redirect()->to('/login');
        }

        $hold = (new EmdHoldModel())->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$hold) {
            return redirect()->back()->with('error', 'You have no EMD deposit on this sale event to dispute.');
        }

        $reason = trim((string) $this->request->getPost('reason')) ?: 'Buyer-initiated chargeback (dev simulation)';

        try {
            (new ChargebackService())->fileChargeback($hold['id'], $reason);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('error', 'Chargeback filed. The evidence package has been assembled automatically.');
    }

    public function index()
    {
        $caseModel = new ChargebackCaseModel();
        return view('admin/chargebacks', [
            'title' => 'Chargeback Handling — AdwitiX',
            'openRepresentment' => $caseModel->findOpenRepresentment(),
            'pendingIntegrityReview' => $caseModel->findPendingIntegrityReview(),
            'resolved' => $caseModel->findResolved(),
        ]);
    }

    public function decide(string $caseId)
    {
        $adminId = session()->get('super_admin_party_id');
        $outcome = $this->request->getPost('outcome');
        $notes = (string) $this->request->getPost('notes');

        try {
            (new ChargebackService())->recordRepresentmentOutcome($caseId, $adminId, $outcome, $notes);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/chargebacks')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/chargebacks')->with('error', 'Representment outcome recorded.');
    }

    public function reviewIntegrity(string $caseId)
    {
        $adminId = session()->get('super_admin_party_id');
        $applyRatingConsequence = $this->request->getPost('apply_rating_consequence') === '1';
        $notes = (string) $this->request->getPost('notes');

        try {
            (new ChargebackService())->reviewIntegrityFlag($caseId, $adminId, $applyRatingConsequence, $notes);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/chargebacks')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/chargebacks')->with('error', 'Chargeback integrity review recorded.');
    }
}
