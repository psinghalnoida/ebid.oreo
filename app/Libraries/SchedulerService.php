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

    // BR-54/PR-31: "System continuously screens deposit, refund, and
    // transfer activity against defined AML pattern rules" — this is
    // what makes that screening actually continuous rather than a
    // one-off manual run. Flattened to a single list of flagged party
    // IDs (rather than AmlMonitoringService::runAll()'s per-pattern
    // breakdown) so it fits the same count()-based summary every other
    // scheduler method already returns.
    public function processAmlMonitoring(): array
    {
        $byPattern = (new \App\Libraries\AmlMonitoringService())->runAll();
        return array_merge(...array_values($byPattern));
    }

    // BR-50: the mandatory 24-hour cooling-off is what makes a payout
    // bank-detail change genuinely time-gated rather than immediate.
    public function processPendingPayoutBankChanges(): array
    {
        return (new \App\Libraries\PayoutControlService())->promoteDuePendingChanges();
    }

    // Phase 3A: account deletion — genuinely archives (the existing
    // BR-05 logical-archive mechanism) once the 30-day grace period has
    // passed, never before, and never if the party cancelled in the
    // meantime (deletion_requested_at would be null by then).
    public function processAccountDeletions(): array
    {
        $db = \Config\Database::connect();
        $due = $db->table('party')
            ->where('deletion_requested_at IS NOT NULL')
            ->where('deletion_requested_at <=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->where('archived_at', null)
            ->get()->getResultArray();

        $partyModel = new \App\Models\PartyModel();
        $archived = [];
        foreach ($due as $party) {
            $partyModel->update($party['id'], ['archived_at' => date('Y-m-d H:i:s')]);
            (new \App\Libraries\AuditLogService())->log('account.deletion_finalized', null, [
                'partyId' => $party['id'], 'reason' => $party['deletion_reason'],
            ]);
            $archived[] = $party['id'];
        }
        return $archived;
    }

    // PR-09: the same cron sweep that already drains every other
    // time-based queue in this codebase also drains the media upload
    // job queue — one cron entry, not a second separate one.
    public function processMediaQueue(): array
    {
        return (new MediaQueueService())->processAll();
    }

    // Tech Stack §3.10: "checked continuously" — the same cron sweep
    // runs the real SNTP drift check on every pass. Returns the check's
    // own ID as a single-element list only when it actually raised a
    // drift alert, so it composes with runAll()'s existing
    // count()-based summary/audit-log-suppression logic without any
    // special-casing.
    public function processServerTimeCheck(): array
    {
        $check = (new ServerTimeIntegrityService())->runCheck();
        return ($check['ntp_reachable'] && !$check['within_tolerance']) ? [$check['id']] : [];
    }

    // BR-28/D-113: closes a real, previously-undiscovered gap —
    // CascadeService::processDefault() existed and was fully correct,
    // but nothing in the running application ever called it. A cascade
    // could open (a bidder told they owe a top-up) but never actually
    // default, because no scheduled sweep checked for an expired,
    // unpaid window. Symmetric with processExpiredExpressBidding/
    // processExpiredEasyAuctions just above, which open the cascade in
    // the first place — this is what actually closes the loop those
    // two started.
    public function processExpiredCascadeTopups(): array
    {
        $expired = $this->bidModel->findExpiredUnpaidTopups(date('Y-m-d H:i:s'));

        $processed = [];
        foreach ($expired as $bid) {
            try {
                $this->cascade->processDefault($bid['sale_event_id'], $bid['id']);
                $processed[] = $bid['id'];
            } catch (\RuntimeException $e) {
                // Already handled (e.g. a concurrent sweep or a payment
                // that landed in the same instant) — skip rather than
                // crash the whole scheduler run over one bad record.
                continue;
            }
        }
        return $processed;
    }

    // BR-32/33 (D-88): consolidates every Tenant's unbilled Seller-Pays
    // Success Fee entries into one monthly invoice. generateMonthlyInvoices()
    // is itself idempotent (a tenant already invoiced for the period has no
    // unbilled entries left), but gated to the 1st of the month here so a
    // frequent cron sweep doesn't needlessly re-scan on every tick.
    public function processTenantMonthlyBilling(): array
    {
        if ((int) date('j') !== 1) {
            return [];
        }
        $invoices = (new TenantBillingService())->generateMonthlyInvoices();
        return array_column($invoices, 'id');
    }

    public function runAll(): array
    {
        $result = [
            'gracePeriodsProcessed' => $this->processExpiredGracePeriods(),
            'expressBiddingClosed' => $this->processExpiredExpressBidding(),
            'easyAuctionsClosed' => $this->processExpiredEasyAuctions(),
            'cascadeTopupsDefaulted' => $this->processExpiredCascadeTopups(),
            'staleOffersLapsed' => $this->processStaleOffers(),
            'settlementsFlaggedStalled' => $this->processStalledSettlements(),
            'mediaWaiversLapsed' => (new TenantMediaWaiverService())->lapseExpired(),
            'standingReviewAnniversariesOpened' => $this->processStandingReviewAnniversaries(),
            'amlFlagsRaised' => $this->processAmlMonitoring(),
            'payoutBankChangesActivated' => $this->processPendingPayoutBankChanges(),
            'accountsArchived' => $this->processAccountDeletions(),
            'mediaJobsProcessed' => $this->processMediaQueue(),
            'serverTimeDriftAlerts' => $this->processServerTimeCheck(),
            'tenantMonthlyInvoicesGenerated' => $this->processTenantMonthlyBilling(),
            // PR-37: retries any webhook delivery still pending, whose
            // next_attempt_at has come due.
            'tenantWebhooksRetried' => (new TenantWebhookService())->retryDue(),
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
