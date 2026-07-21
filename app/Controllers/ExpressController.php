<?php

namespace App\Controllers;

use App\Libraries\ExpressAuctionService;
use App\Models\SaleEventModel;

class ExpressController extends BaseController
{
    private ExpressAuctionService $express;
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->express = new ExpressAuctionService();
        $this->saleEventModel = new SaleEventModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // BR-27/PR-11: pledging = funding EMD. Real payment gateway not yet
    // integrated (same stand-in category as BidController::devFundEmd,
    // OfferController::devFundEmd) — this simulates a cleared payment,
    // and is also the real trigger check (3rd distinct pledge auto-opens
    // bidding), which is NOT a stand-in — that part is real.
    public function pledge(string $saleEventId)
    {
        $buyerId = $this->requireLogin();
        if (!$buyerId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->express->pledgeReserve($saleEventId, $buyerId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
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
            $this->express->placeBid($saleEventId, $bidderId, $amount);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // ⚠️ DEV-ONLY: forces the 1-hour bidding countdown to expire
    // immediately. Gated behind the same tenantAdmin filter as other
    // administrative time-skips (see D-17/D-19 pattern).
    public function devForceCloseBidding(string $saleEventId)
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->express->devForceCloseBidding($saleEventId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }
}
