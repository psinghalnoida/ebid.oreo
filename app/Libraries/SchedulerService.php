<?php

namespace App\Libraries;

use App\Models\SaleEventModel;
use App\Models\BidModel;

// This is the piece that turns every previously-manual "dev-force"
// timer into something that actually runs on its own. Intended to be
// called from a real cron entry (documented in SETUP.md) — not itself a
// cron daemon, since PHP/CodeIgniter has no built-in scheduler.
//
// ⚠️ HONEST LIMITATION: Easy Auction was never given a defined "bidding
// ends at time X" mechanism anywhere in this codebase — only Express got
// an explicit countdown (the pledge-triggered 1-hour window). This
// scheduler cannot close an Easy Auction's bidding phase automatically
// because no such trigger point exists yet. This is a real, separate gap
// from what this service closes — flagged here rather than implied fixed.
class SchedulerService
{
    private SaleEventModel $saleEventModel;
    private BidModel $bidModel;
    private ListingLifecycleService $lifecycle;
    private CascadeService $cascade;
    private OfferService $offers;
    private SettlementService $settlement;

    public function __construct()
    {
        $this->saleEventModel = new SaleEventModel();
        $this->bidModel = new BidModel();
        $this->lifecycle = new ListingLifecycleService();
        $this->cascade = new CascadeService();
        $this->offers = new OfferService();
        $this->settlement = new SettlementService();
    }

    // BR-14: auto-freeze any Easy/Buy-Now sale_event whose 60-minute
    // grace window has genuinely expired.
    public function processExpiredGracePeriods(): array
    {
        $db = \Config\Database::connect();
        $expired = $db->table('sale_event')
            ->where('status', 'grace_period')
            ->where('grace_period_ends_at <', date('Y-m-d H:i:s'))
            ->get()->getResultArray();

        $processed = [];
        foreach ($expired as $saleEvent) {
            try {
                $this->lifecycle->freezeAfterGrace($saleEvent['id']);
                $processed[] = $saleEvent['id'];
            } catch (\RuntimeException $e) {
                // Already handled or in an unexpected state — skip rather
                // than crash the whole scheduler run over one bad record.
                continue;
            }
        }
        return $processed;
    }

    // PR-11: auto-initiate the cascade once Express's real 1-hour bidding
    // window has genuinely expired. This was a real gap — nothing
    // previously did this automatically at all, dev or otherwise.
    public function processExpiredExpressBidding(): array
    {
        $db = \Config\Database::connect();
        $candidates = $db->table('sale_event')
            ->where('sale_format', 'express')
            ->where('status', 'active')
            ->where('scheduled_start_at IS NOT NULL')
            ->where('scheduled_end_at <', date('Y-m-d H:i:s'))
            ->get()->getResultArray();

        $processed = [];
        foreach ($candidates as $saleEvent) {
            // Guard against re-triggering: if H1 already has a topup
            // window set, cascade was already initiated for this event.
            $ranked = $this->bidModel->findRankedBids($saleEvent['id'], 1);
            if (empty($ranked) || $ranked[0]['topup_required_by'] !== null) {
                continue;
            }
            try {
                $this->cascade->initiateCascade($saleEvent['id']);
                $processed[] = $saleEvent['id'];
            } catch (\RuntimeException $e) {
                continue;
            }
        }
        return $processed;
    }

    // BR: Buy-Now offers lapse unactioned after 3 days, no reason required
    public function processStaleOffers(): array
    {
        $lapsed = $this->offers->lapseStaleOffers();
        return array_column($lapsed, 'id');
    }

    // BR-39: flag settlements that have sat incomplete past the threshold
    public function processStalledSettlements(): array
    {
        return $this->settlement->flagStalledSettlements();
    }

    // Runs everything in one pass — this is what the real cron entry calls.
    public function runAll(): array
    {
        return [
            'gracePeriodsProcessed' => $this->processExpiredGracePeriods(),
            'expressBiddingClosed' => $this->processExpiredExpressBidding(),
            'staleOffersLapsed' => $this->processStaleOffers(),
            'settlementsFlaggedStalled' => $this->processStalledSettlements(),
        ];
    }
}
