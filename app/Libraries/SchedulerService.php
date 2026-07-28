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

    // BR-12/Dynamic Time: auto-initiate the cascade once an Easy
    // Auction's seller-set schedule has genuinely ended — accounting for
    // any Dynamic Time extensions that may have pushed the end time
    // later than originally set.
    public function processExpiredEasyAuctions(): array
    {
        $db = \Config\Database::connect();
        $candidates = $db->table('sale_event')
            ->where('sale_format', 'easy')
            ->where('status', 'active')
            ->where('scheduled_end_at IS NOT NULL')
            ->where('scheduled_end_at <', date('Y-m-d H:i:s'))
            ->get()->getResultArray();

        $processed = [];
        foreach ($candidates as $saleEvent) {
            $ranked = $this->bidModel->findRankedBids($saleEvent['id'], 1);

            if (empty($ranked)) {
                // No bids at all when the schedule ended — this must
                // still resolve, not sit open forever.
                $this->saleEventModel->markClosed($saleEvent['id'], 'cycle_ended_unsold');
                $processed[] = $saleEvent['id'];
                continue;
            }
            if ($ranked[0]['topup_required_by'] !== null) {
                continue; // already cascaded on a prior scheduler run
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
    // BR-61: checks every approved seller's annual Standing Review
    // anniversary — a distinct trigger from the count-based one, which
    // fires immediately via StandingReviewService::recordComplaint.
    public function processStandingReviewAnniversaries(): array
    {
        $db = \Config\Database::connect();
        $sellerIds = $db->table('seller_application')->distinct()->select('party_id')
            ->where('status', 'approved')->get()->getResultArray();

        $standingReview = new StandingReviewService();
        $opened = [];
        foreach ($sellerIds as $row) {
            if ($standingReview->checkAnnualAnniversary($row['party_id'])) {
                $opened[] = $row['party_id'];
            }
        }
        return $opened;
    }

    // BR-50/PR-28: cooling-off accounts whose 24h window has genuinely lapsed.
    public function processBankAccountActivations(): array
    {
        return (new PayoutAccountService())->activateDueAccounts();
    }

    // BR-50: retries every settlement currently on hold — either its
    // cooling-off window has now lapsed, or a Tenant/SaaS Admin has since
    // released a flagged high-value hold. Idempotent: a settlement that's
    // still genuinely blocked just stays 'payout_held' for the next pass.
    public function processPayoutHoldRetries(): array
    {
        $held = (new \App\Models\SettlementModel())->findPayoutHeld();

        $released = [];
        foreach ($held as $row) {
            $result = $this->settlement->retryPayoutHold($row['id']);
            if ($result['status'] === 'completed') {
                $released[] = $row['id'];
            }
        }
        return $released;
    }

    public function runAll(): array
    {
        $result = [
            'gracePeriodsProcessed' => $this->processExpiredGracePeriods(),
            'expressBiddingClosed' => $this->processExpiredExpressBidding(),
            'easyAuctionsClosed' => $this->processExpiredEasyAuctions(),
            'staleOffersLapsed' => $this->processStaleOffers(),
            'settlementsFlaggedStalled' => $this->processStalledSettlements(),
            'mediaWaiversLapsed' => (new TenantMediaWaiverService())->lapseExpired(),
            'standingReviewAnniversariesOpened' => $this->processStandingReviewAnniversaries(),
            'amlFlagsCreated' => (new AmlMonitoringService())->runScreening(),
            'bankAccountsActivated' => $this->processBankAccountActivations(),
            'payoutHoldsReleased' => $this->processPayoutHoldRetries(),
        ];

        // BR-05: every scheduler run is a genuine "configuration/state
        // change" event, even with zero human present — actor is
        // deliberately null (system-triggered, not a person's decision).
        // Logged as one summary record per run rather than one entry per
        // individual sale_event/offer touched, since a busy scheduler run
        // could otherwise flood the log with dozens of near-identical
        // entries for what is fundamentally one automatic sweep.
        $totalActions = array_sum(array_map('count', $result));
        if ($totalActions > 0) {
            (new \App\Libraries\AuditLogService())->log('scheduler.run', null, $result);
        }

        return $result;
    }
}
