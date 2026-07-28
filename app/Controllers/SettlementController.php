<?php

namespace App\Controllers;

use App\Libraries\SettlementService;
use App\Libraries\PayoutAccountService;
use App\Libraries\AuthorizationService;
use App\Models\SettlementModel;
use App\Models\SaleEventModel;
use App\Models\PayoutHoldModel;

class SettlementController extends BaseController
{
    private SettlementService $settlement;
    private SettlementModel $settlementModel;
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->settlement = new SettlementService();
        $this->settlementModel = new SettlementModel();
        $this->saleEventModel = new SaleEventModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function show(string $settlementId)
    {
        $s = $this->settlementModel->find($settlementId);
        if (!$s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $saleEvent = $this->saleEventModel->find($s['sale_event_id']);
        $invoices = (new \App\Libraries\InvoiceService())->findForSettlement($settlementId);

        $callerId = $this->requireLogin();
        $auth = new AuthorizationService();
        $isReviewAdmin = $callerId && (
            $auth->isTenantAdminForSettlement($callerId, $settlementId) || $auth->isSuperAdmin($callerId)
        );
        $payoutHold = null;
        if ($s['status'] === 'payout_held') {
            // Most recent hold regardless of status — a 'rejected' hold
            // still needs to render distinctly from "still cooling off,
            // no hold at all," not silently fall through to that message.
            $payoutHold = (new PayoutHoldModel())->where('settlement_id', $settlementId)->orderBy('created_at', 'DESC')->first();
        }

        return view('settlement/show', [
            'title' => 'Settlement — eBid Hub', 'settlement' => $s, 'saleEvent' => $saleEvent,
            'callerId' => $callerId, 'invoices' => $invoices,
            'isReviewAdmin' => $isReviewAdmin, 'payoutHold' => $payoutHold,
        ]);
    }

    public function confirmSellerNoc(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->settlement->confirmSellerNoc($settlementId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function confirmBuyerNoc(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->settlement->confirmBuyerNoc($settlementId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function rateAsBuyer(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $outcome = $this->request->getPost('outcome');
        $reason = $this->request->getPost('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, $partyId, 'buyer', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function rateAsSeller(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $outcome = $this->request->getPost('outcome');
        $reason = $this->request->getPost('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, $partyId, 'seller', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    // ⚠️ DEV-ONLY: BR-39's real 7-day stall wait can't be tested live —
    // forces the flag check to run immediately. Gated behind tenantAdmin.
    public function devFlagStalled()
    {
        $flagged = $this->settlement->flagStalledSettlements();
        return $this->response->setJSON(['flagged' => $flagged]);
    }

    // Real admin action (once flagged), not a time-skip — genuinely
    // gated behind tenantAdmin since force-resolving is an administrative act.
    public function forceResolve(string $settlementId)
    {
        try {
            $this->settlement->forceResolveStalled($settlementId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    // BR-50(c): "Tenant Admin OR SaaS Admin" — dual authorization, so this
    // deliberately isn't behind a single route filter the way forceResolve
    // is; both paths are checked explicitly here.
    public function payoutHoldDecide(string $settlementId)
    {
        $callerId = $this->requireLogin();
        if (!$callerId) return redirect()->to('/login');

        $auth = new AuthorizationService();
        if (!$auth->isTenantAdminForSettlement($callerId, $settlementId) && !$auth->isSuperAdmin($callerId)) {
            return service('response')->setStatusCode(403)->setBody('BR-50: only this settlement\'s Tenant Admin or a SaaS Admin may decide a payout hold.');
        }

        $hold = (new PayoutHoldModel())->where('settlement_id', $settlementId)->where('status', 'pending')->first();
        if (!$hold) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', 'No pending payout hold for this settlement.');
        }

        $outcome = $this->request->getPost('outcome') === 'release' ? 'released' : 'rejected';
        $notes = $this->request->getPost('notes');

        try {
            (new PayoutAccountService())->reviewHold($hold['id'], $callerId, $outcome, $notes);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }

        if ($outcome === 'released') {
            // Immediate retry rather than waiting for the next scheduler
            // pass — the admin just explicitly authorized this release.
            $this->settlement->retryPayoutHold($settlementId);
        }

        return redirect()->to("/settlements/{$settlementId}");
    }
}
