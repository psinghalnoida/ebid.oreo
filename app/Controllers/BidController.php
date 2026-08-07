<?php

namespace App\Controllers;

use App\Libraries\BiddingService;
use App\Libraries\CascadeService;
use App\Libraries\EmdService;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\BidModel;
use App\Libraries\Uuid;

class BidController extends BaseController
{
    private BiddingService $bidding;
    private CascadeService $cascade;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;
    private BidModel $bidModel;

    public function __construct()
    {
        $this->bidding = new BiddingService();
        $this->cascade = new CascadeService();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->bidModel = new BidModel();
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

        // BR-15: structurally barred from pledging under any
        // circumstance — checked before the EMD is ever held, not just
        // at the later bid.
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($bidderId)) {
            return redirect()->to('/')->with('error', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never pledge an EMD deposit.');
        }

        // BR-55: full KYC verification is mandatory before a User's
        // first EMD pledge, with no lower-value exemption.
        try {
            (new \App\Libraries\KycService())->requireVerifiedKyc($bidderId, 'pledging an EMD deposit');
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $baseline = EmdService::calculateBaselineEmd(
            $saleEvent['sale_format'],
            $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
            $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null
        );

        $existing = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $bidderId);
        if (!$existing || $existing['status'] !== 'held') {
            // BR-55: enhanced due diligence above the live threshold —
            // gates this specific pledge, not the whole account.
            try {
                (new \App\Libraries\KycService())->checkEnhancedDueDiligence($bidderId, $baseline);
            } catch (\RuntimeException $e) {
                return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
            }
            $this->emdHoldModel->createHold($saleEventId, $bidderId, 'van', $baseline);
            (new \App\Libraries\AuditLogService())->log('emd.held', $bidderId, [
                'saleEventId' => $saleEventId, 'amount' => $baseline, 'channel' => 'van',
            ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // ⚠️ DEV-ONLY: simulates a cleared cascade top-up payment (BR-28),
    // same convention as devFundEmd above — the real flow routes
    // through the same not-yet-integrated payment gateway. D-113:
    // closes the other half of a real, previously-undiscovered gap —
    // CascadeService::processTopupPaid() was fully correct but no
    // route anywhere let a bidder actually reach it.
    public function devPayTopup(string $saleEventId)
    {
        $bidderId = $this->requireLogin();
        if (!$bidderId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Only the specific bidder actually holding this sale event's
        // open top-up window may pay it — resolved from the caller's
        // own identity, never trusted from client input.
        $bid = $this->bidModel->findOpenTopupForBidder($saleEventId, $bidderId);
        if (!$bid) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")
                ->with('error', 'BR-28: you have no open top-up window on this sale event.');
        }

        if (strtotime($bid['topup_required_by']) < time()) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")
                ->with('error', 'BR-28: this top-up window has already expired.');
        }

        try {
            $this->cascade->processTopupPaid($bid['id']);
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
            (new \App\Libraries\EasyAuctionService())->placeBid($saleEventId, $bidderId, $amount);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }
}
