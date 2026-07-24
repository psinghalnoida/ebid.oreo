<?php

namespace App\Libraries;

use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\BidModel;

// BR-12/PR-11: Express Auction Trigger Sequence — the seller sets a
// Reserve Value; the auction does NOT open for bidding immediately.
// Buyers "pledge" (fund EMD) to express intent; on the 3rd distinct
// pledge, the bidding phase launches automatically for a fixed run
// window. Reuses sale_event.scheduled_start_at (set = bidding phase has
// begun) and scheduled_end_at (bidding phase deadline) rather than adding
// new schema, since those columns existed but were unused until now.
class ExpressAuctionService
{
    private const PLEDGES_REQUIRED = 3;       // PR-11: "on the 3rd distinct pledge"
    private const BIDDING_WINDOW_MINUTES = 60; // "1-hour run time"

    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;
    private BidModel $bidModel;
    private BiddingService $bidding;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->bidModel = new BidModel();
        $this->bidding = new BiddingService();
    }

    // BR-27: 10% of RV, same baseline as Easy — pledging IS funding EMD.
    // If this is the 3rd distinct buyer to pledge, automatically opens
    // the bidding phase (PR-11 step: "on the 3rd distinct pledge...
    // schedules the bidding phase").
    public function pledgeReserve(string $saleEventId, string $buyerPartyId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'express') {
            throw new \RuntimeException('ExpressAuctionService is only for Express sale events');
        }

        // BR-21/BR-22: conflict-of-interest block
        $conflict = (new AuthorizationService())->hasConflictOfInterest($buyerPartyId, $saleEvent['listing_id']);
        if ($conflict) {
            throw new \RuntimeException($conflict);
        }

        if ($saleEvent['status'] !== 'active') {
            throw new \RuntimeException("Cannot pledge on a sale_event with status={$saleEvent['status']}");
        }

        $baseline = EmdService::calculateBaselineEmd('express', null, (float) $saleEvent['reserve_value']);
        $existing = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerPartyId);
        if (!$existing || $existing['status'] !== 'held') {
            $this->emdHoldModel->createHold($saleEventId, $buyerPartyId, 'van', $baseline);
        }

        $pledgeCount = $this->pledgeCount($saleEventId);

        // Only trigger once, exactly at the moment the 3rd distinct pledge lands
        if ($pledgeCount === self::PLEDGES_REQUIRED && $saleEvent['scheduled_start_at'] === null) {
            $start = new \DateTimeImmutable();
            $end = $start->modify('+' . self::BIDDING_WINDOW_MINUTES . ' minutes');
            $this->saleEventModel->update($saleEventId, [
                'scheduled_start_at' => $start->format('Y-m-d H:i:s'),
                'scheduled_end_at' => $end->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->saleEventModel->find($saleEventId);
    }

    public function pledgeCount(string $saleEventId): int
    {
        return $this->emdHoldModel->where('sale_event_id', $saleEventId)->where('status', 'held')->countAllResults();
    }

    // Bidding is only open once the 3rd pledge has triggered the phase,
    // and only until the fixed run window expires.
    public function isBiddingOpen(array $saleEvent): bool
    {
        if ($saleEvent['scheduled_start_at'] === null) {
            return false;
        }
        return new \DateTimeImmutable() < new \DateTimeImmutable($saleEvent['scheduled_end_at']);
    }

    // BR-43/BR-27 enforcement delegated to the already-tested BiddingService
    // — this layer only adds the Express-specific phase gate on top.
    public function placeBid(string $saleEventId, string $bidderPartyId, float $amount): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['sale_format'] !== 'express') {
            throw new \RuntimeException('ExpressAuctionService is only for Express sale events');
        }
        if (!$this->isBiddingOpen($saleEvent)) {
            throw new \RuntimeException(
                $saleEvent['scheduled_start_at'] === null
                    ? 'Bidding has not opened yet — awaiting the 3rd buyer pledge (PR-11)'
                    : 'The 1-hour Express bidding window has closed'
            );
        }
        $result = $this->bidding->placeBid($saleEventId, $bidderPartyId, $amount);
        $this->applyIncrementHalvingIfNeeded($saleEventId);
        return $result;
    }

    // D-34 correction: Express was missing the increment-halving
    // requirement entirely — the general engine spec describes this as
    // applying platform-wide, not just to Easy/Tender. Unlike Easy/Tender,
    // Express's clock itself never extends (fixed 1-hour window,
    // confirmed explicitly) — only the increment behavior applies here.
    public function applyIncrementHalvingIfNeeded(string $saleEventId): void
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if ($saleEvent['increment_halved_at'] !== null || $saleEvent['bid_increment_amount'] === null || $saleEvent['scheduled_end_at'] === null) {
            return;
        }
        $triggerMinutes = (int) ($saleEvent['dynamic_time_trigger_minutes'] ?? 10);
        $currentEnd = new \DateTimeImmutable($saleEvent['scheduled_end_at']);
        $triggerThreshold = $currentEnd->modify("-{$triggerMinutes} minutes");

        if (new \DateTimeImmutable() >= $triggerThreshold) {
            $this->saleEventModel->update($saleEventId, [
                'bid_increment_amount' => round((float) $saleEvent['bid_increment_amount'] / 2, 2),
                'increment_halved_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // ⚠️ DEV-ONLY: forces the countdown to expiry immediately, since a
    // real 1-hour wait can't be tested live. Same category of stand-in as
    // ListingLifecycleService's grace-window force-freeze.
    public function devForceCloseBidding(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['scheduled_start_at'] === null) {
            throw new \RuntimeException('Bidding phase has not started yet (3rd pledge required first)');
        }
        $this->saleEventModel->update($saleEventId, [
            'scheduled_end_at' => date('Y-m-d H:i:s', time() - 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->saleEventModel->find($saleEventId);
    }
}
