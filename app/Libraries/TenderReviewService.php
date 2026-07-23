<?php

namespace App\Libraries;

use App\Models\TenderReviewModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\ListingModel;
use App\Models\EmdHoldModel;

class TenderReviewService
{
    private TenderReviewModel $reviewModel;
    private SaleEventModel $saleEventModel;
    private BidModel $bidModel;
    private ListingModel $listingModel;
    private EmdHoldModel $emdHoldModel;
    private AuthorizationService $authz;

    public function __construct()
    {
        $this->reviewModel = new TenderReviewModel();
        $this->saleEventModel = new SaleEventModel();
        $this->bidModel = new BidModel();
        $this->listingModel = new ListingModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->authz = new AuthorizationService();
    }

    public function closeBiddingAndDeclareProvisional(string $saleEventId, string $sellerId): array
    {
        $saleEvent = $this->requireTenderEvent($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if ($listing['seller_party_id'] !== $sellerId) {
            throw new \RuntimeException('Only the listing\'s seller may close bidding and declare a provisional winner.');
        }

        $existing = $this->reviewModel->findCurrentForSaleEvent($saleEventId);
        if ($existing) {
            throw new \RuntimeException('This Tender is already in review — cannot restart.');
        }

        $currentHigh = $this->bidModel->findCurrentHighBid($saleEventId);
        if (!$currentHigh) {
            throw new \RuntimeException('No bids were placed — nothing to declare a provisional winner from.');
        }

        return $this->reviewModel->createReview($saleEventId, $currentHigh['id'], $currentHigh['bidder_party_id'], 1);
    }

    public function grantExtension(string $reviewId, string $tenantAdminId, string $reason): array
    {
        $review = $this->requireReview($reviewId);
        $this->requireTenantAdminFor($review['sale_event_id'], $tenantAdminId);
        if ($review['status'] !== 'provisional') {
            throw new \RuntimeException('An extension can only be granted on a provisional review.');
        }

        $this->reviewModel->update($reviewId, [
            'status' => 'extension_granted', 'extension_reason' => $reason,
            'extension_granted_by_party_id' => $tenantAdminId, 'extension_granted_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->reviewModel->find($reviewId);
    }

    public function rejectAndCascade(string $reviewId, string $tenantAdminId, string $reason): array
    {
        $review = $this->requireReview($reviewId);
        $this->requireTenantAdminFor($review['sale_event_id'], $tenantAdminId);
        if (!in_array($review['status'], ['provisional', 'extension_granted'], true)) {
            throw new \RuntimeException('Only a provisional or extended review can be rejected.');
        }

        $this->reviewModel->update($reviewId, [
            'status' => 'rejected', 'rejection_reason' => $reason,
            'rejected_by_party_id' => $tenantAdminId, 'rejected_at' => date('Y-m-d H:i:s'),
        ]);

        $rejectedHold = $this->emdHoldModel->findBySaleEventAndParty($review['sale_event_id'], $review['party_id']);
        if ($rejectedHold && $rejectedHold['status'] === 'held') {
            $this->emdHoldModel->markReleased($rejectedHold['id']);
        }

        $priorRounds = $this->reviewModel->findAllForSaleEvent($review['sale_event_id']);
        $alreadyReviewedPartyIds = array_column($priorRounds, 'party_id');

        $ranked = $this->bidModel->findRankedBids($review['sale_event_id'], 50);
        $nextBid = null;
        foreach ($ranked as $bid) {
            if (!in_array($bid['bidder_party_id'], $alreadyReviewedPartyIds, true)) {
                $nextBid = $bid;
                break;
            }
        }

        if (!$nextBid) {
            $this->saleEventModel->markClosed($review['sale_event_id'], 'cycle_ended_unsold');
            return $this->reviewModel->find($reviewId);
        }

        return $this->reviewModel->createReview(
            $review['sale_event_id'], $nextBid['id'], $nextBid['bidder_party_id'], $review['round_number'] + 1
        );
    }

    public function confirmWinner(string $reviewId, string $tenantAdminId): array
    {
        $review = $this->requireReview($reviewId);
        $this->requireTenantAdminFor($review['sale_event_id'], $tenantAdminId);
        if (!in_array($review['status'], ['provisional', 'extension_granted'], true)) {
            throw new \RuntimeException('Only a provisional or extended review can be confirmed.');
        }

        $bid = $this->bidModel->find($review['bid_id']);
        $this->reviewModel->update($reviewId, [
            'status' => 'confirmed', 'confirmed_by_party_id' => $tenantAdminId, 'confirmed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->saleEventModel->markClosed($review['sale_event_id'], 'closed_sold');
        $this->saleEventModel->updateCurrentPrice($review['sale_event_id'], (float) $bid['amount'], $review['party_id']);

        (new SettlementService())->createForSaleEvent($review['sale_event_id'], $review['party_id'], (float) $bid['amount']);

        return $this->reviewModel->find($reviewId);
    }

    public function generateAuctionReport(string $saleEventId): array
    {
        $this->requireTenderEvent($saleEventId);
        $tenderService = new TenderService();

        return [
            'interested' => (new \App\Models\TenderInterestModel())->findForSaleEvent($saleEventId),
            'eligible' => (new \App\Models\TenderEligibilityModel())->findForSaleEvent($saleEventId),
            'bidHistory' => $this->bidModel->where('sale_event_id', $saleEventId)->orderBy('placed_at', 'ASC')->findAll(),
            'emdLog' => $tenderService->getEmdLog($saleEventId),
            'reviewRounds' => $this->reviewModel->findAllForSaleEvent($saleEventId),
        ];
    }

    public function getReview(string $reviewId): array
    {
        return $this->requireReview($reviewId);
    }

    public function getCurrentReview(string $saleEventId): ?array
    {
        return $this->reviewModel->findCurrentForSaleEvent($saleEventId);
    }

    private function requireReview(string $reviewId): array
    {
        $review = $this->reviewModel->find($reviewId);
        if (!$review) {
            throw new \RuntimeException('Review not found');
        }
        return $review;
    }

    private function requireTenantAdminFor(string $saleEventId, string $partyId): void
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$this->authz->isTenantAdminFor($partyId, $saleEvent['tenant_id'])) {
            throw new \RuntimeException('Only the Tenant Admin may act on behalf of insurer/insured/surveyor for this Tender.');
        }
    }

    private function requireTenderEvent(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'tender') {
            throw new \RuntimeException('This is not a Tender Auction sale event.');
        }
        return $saleEvent;
    }
}
