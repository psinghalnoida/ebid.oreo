<?php

namespace App\Controllers;

use App\Libraries\BiddingService;
use App\Libraries\EmdService;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Libraries\Uuid;

class BidController extends BaseController
{
    private BiddingService $bidding;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->bidding = new BiddingService();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // ⚠️ DEV-ONLY: simulates a cleared EMD payment. The real flow (BR-26)
    // routes through a payment gateway (VAN/credit card) — not yet
    // integrated (tech-stack open item, provider TBD). This exists purely
    // so the bidding flow can be demonstrated/tested end-to-end without a
    // real payment gateway connected.
    public function devFundEmd(string $saleEventId)
    {
        $bidderId = $this->requireLogin();
        if (!$bidderId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $baseline = EmdService::calculateBaselineEmd(
            $saleEvent['sale_format'],
            $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
            $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null
        );

        $existing = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $bidderId);
        if (!$existing || $existing['status'] !== 'held') {
            $this->emdHoldModel->createHold($saleEventId, $bidderId, 'van', $baseline);
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function placeBid(string $saleEventId)
    {
        $bidderId = $this->requireLogin();
        if (!$bidderId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $amount = (float) $this->request->getPost('amount');

        try {
            $this->bidding->placeBid($saleEventId, $bidderId, $amount);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }
}
