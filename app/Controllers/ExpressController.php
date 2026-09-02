<?php

namespace App\Controllers;

use App\Libraries\ExpressAuctionService;
use App\Libraries\UserAuthContext;
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

    // BR-27/PR-11: pledging = funding EMD. Real payment gateway not yet
    // integrated (same stand-in category as BidController::devFundEmd,
    // OfferController::devFundEmd) — this simulates a cleared payment,
    // and is also the real trigger check (3rd distinct pledge auto-opens
    // bidding), which is NOT a stand-in — that part is real.
    public function pledge(string $saleEventId)
    {
        $buyerId = UserAuthContext::partyId();

        try {
            $this->express->pledgeReserve($saleEventId, $buyerId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'pledge_failed', $e->getMessage());
        }

        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }

    public function placeBid(string $saleEventId)
    {
        $bidderId = UserAuthContext::partyId();
        $amount = (float) $this->input('amount');

        try {
            $this->express->placeBid($saleEventId, $bidderId, $amount);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'bid_failed', $e->getMessage());
        }

        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }

    // ⚠️ DEV-ONLY: forces the 1-hour bidding countdown to expire
    // immediately. Gated behind jwtTenantAdmin, same as other
    // administrative time-skips (see D-17/D-19 pattern).
    public function devForceCloseBidding(string $saleEventId)
    {
        try {
            $this->express->devForceCloseBidding($saleEventId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'force_close_failed', $e->getMessage());
        }
        return $this->apiResponse(['saleEvent' => $this->saleEventModel->find($saleEventId)]);
    }
}
