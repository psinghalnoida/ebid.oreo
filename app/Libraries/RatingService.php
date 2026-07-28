<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\RatingEventModel;

class RatingService
{
    private const DEFAULT_RATING = 3.0;
    private const CRAWL_BACK_THRESHOLD = 2.0;
    private const SHADOW_BAN_THRESHOLD = 1.5; // Confirmed by the project owner (see docs/DECISIONS.md D-50) — was an unconfirmed placeholder since D-08
    private const PLATFORM_FLOOR = 1.0;
    // ⚠️ Unconfirmed placeholder, same status Shadow Ban's threshold had
    // until this session — the BR/PR document specifies a "flat
    // transaction ceiling" exists at the 1★ floor but never states the
    // actual rupee value. Flagged for the project owner to confirm or
    // adjust, not silently treated as settled.
    private const PLATFORM_FLOOR_TRANSACTION_CEILING = 10000.0;
    private const DEPOSIT_OVERRIDE_FLOOR = 50000;
    private const FORCED_NEUTRAL_PATTERN_LIMIT = 5;

    // BR-38: Crawl-Back clean-transaction requirement escalates by offence
    // count (3 / 5 / 8, settled in prior project work — see D-08 note).
    private const CRAWL_BACK_CLEAN_REQUIRED_BY_OFFENCE = [1 => 3, 2 => 3, 3 => 5, 4 => 5, 5 => 8];

    // BR-35: general clean-transaction streak reward, distinct from
    // BR-38's crawl_back_clean_completed_* (only counts during active
    // rehabilitation) — this accrues for every party regardless.
    private const SUSTAINED_CLEAN_STREAK_TARGET = 10;

    // BR-35's full graduated event tables — a real, structured data
    // source, not just points scattered across caller code. 'reset_to_1'
    // is a special magnitude: not a relative delta, an absolute floor
    // reset (still routed through the normal BR-36 approval gate, same
    // as any other severe downgrade — see applyNamedEvent).
    //
    // Not every event below has a real trigger wired yet — see
    // docs/DECISIONS.md for exactly which are wired vs. genuinely
    // blocked (no messaging system, no KYC flow, no real payment
    // gateway) and flagged as such rather than faked.
    private const NAMED_EVENTS = [
        'star_rating' => [
            // Small (0.1-0.3)
            'high_participation' => 0.1,
            'prompt_seller_query_response' => 0.1,
            'prompt_noc_confirmation' => 0.1,
            'prompt_rating_submission' => 0.1,
            'successful_collection' => 0.2,
            'clean_inspection' => 0.3,
            'repeated_weak_withdrawal_reasons' => -0.2,
            // Medium (0.4-0.7)
            'early_settlement' => 0.5,
            'sustained_clean_streak' => 0.6,
            'late_payment' => -0.5,
            'stalling_pattern' => -0.5,
            'repeated_baseless_dispute_filing' => -0.6,
            // Large (0.8+)
            'frivolous_dispute' => -1.5,
            'default_1st' => -1.0,
            'default_2nd' => -1.5,
            'default_3rd' => -2.0,
            'disruptive_conduct_harassment' => -1.5,
            'confirmed_fishing_circumvention' => -1.5,
            'chargeback_against_approved_forfeiture' => -2.0,
            'confirmed_false_kyc' => 'reset_to_1',
        ],
        'seller_star_rating' => [
            // Small (0.1-0.3)
            'prompt_noc_confirmation' => 0.1,
            'prompt_rating_submission' => 0.1,
            'fulfilling_promised_shipping' => 0.1,
            'detailed_documentation' => 0.2,
            'rapid_handover' => 0.3,
            // Medium (0.4-0.7)
            'accurate_description' => 0.4,
            'sustained_clean_streak' => 0.6,
            'delayed_collection' => -0.5,
            'stalling_pattern' => -0.5,
            'repeated_baseless_rejection' => -0.6,
            // Large (0.8+)
            'unprofessional_conduct' => -1.0,
            'data_mismatch' => -1.5,
            'dishonest_defect_disclosure' => -1.5,
            'confirmed_offplatform_solicitation' => -1.5,
            'confirmed_cbs_violation' => -2.0,
            'confirmed_fraud' => 'reset_to_1',
            'confirmed_kyc_fraud' => 'reset_to_1',
        ],
    ];

    private const EVENT_LABELS = [
        'high_participation' => 'High Participation (5+ bids in 30 days)',
        'prompt_seller_query_response' => 'Prompt seller-query responses during a live deal',
        'prompt_noc_confirmation' => 'Prompt NOC confirmation',
        'prompt_rating_submission' => 'Prompt rating submission',
        'successful_collection' => 'Successful Collection (clean handover)',
        'clean_inspection' => 'Clean Inspection (no dispute after an Easy Auction win)',
        'repeated_weak_withdrawal_reasons' => 'Repeated weak/unconvincing withdrawal reasons (pattern)',
        'early_settlement' => 'Early Settlement (balance paid within 48 hours of H1)',
        'sustained_clean_streak' => 'Sustained clean streak (10 consecutive clean transactions)',
        'late_payment' => 'Late Payment (more than 7 days from H1)',
        'stalling_pattern' => 'Stalling pattern (5th forced-neutral-rating instance, BR-39)',
        'repeated_baseless_dispute_filing' => 'Repeated baseless dispute-filing pattern',
        'frivolous_dispute' => 'Frivolous Dispute (baseless condition complaint after winning)',
        'default_1st' => '1st Default (non-payment, non-lifting, disruptive conduct)',
        'default_2nd' => '2nd Default (within the 12-month window)',
        'default_3rd' => '3rd Default (within the same window)',
        'disruptive_conduct_harassment' => 'Disruptive conduct or harassment at handover',
        'confirmed_fishing_circumvention' => 'Confirmed fishing/circumvention pattern (reveal-then-abandon)',
        'chargeback_against_approved_forfeiture' => 'Chargeback filed against an already-approved, legitimate forfeiture',
        'confirmed_false_kyc' => 'Confirmed false KYC information',
        'fulfilling_promised_shipping' => 'Fulfilling promised shipping exactly as stated',
        'detailed_documentation' => 'Detailed Documentation (video + photos + location/map)',
        'rapid_handover' => 'Rapid Handover (NOC/delivery within 24 hours of payment)',
        'accurate_description' => 'Accurate Description (buyers consistently rate listing accuracy highly)',
        'delayed_collection' => 'Delayed Collection (unreachable at handover)',
        'repeated_baseless_rejection' => 'Repeated baseless Easy-Auction rejection pattern',
        'unprofessional_conduct' => 'Unprofessional Conduct (verified buyer complaint)',
        'data_mismatch' => 'Data Mismatch (significant discrepancy)',
        'dishonest_defect_disclosure' => 'Dishonest Express defect-disclosure (confirmed via dispute)',
        'confirmed_offplatform_solicitation' => 'Confirmed off-platform solicitation (seller-side fishing)',
        'confirmed_cbs_violation' => 'Confirmed CBS violation (stock/fake photo, past warning stage)',
        'confirmed_fraud' => 'Confirmed fraud (bid manipulation, deliberate misrepresentation)',
        'confirmed_kyc_fraud' => 'Confirmed KYC fraud',
    ];

    private PartyModel $partyModel;
    private RatingEventModel $ratingEventModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->ratingEventModel = new RatingEventModel();
    }

    private function cleanRequiredFor(int $offenceCount): int
    {
        $step = min($offenceCount, 5);
        return self::CRAWL_BACK_CLEAN_REQUIRED_BY_OFFENCE[$step] ?? 8;
    }

    private function roleColumnFor(string $ratingRole): string
    {
        return $ratingRole === 'star_rating' ? 'buyer' : 'seller';
    }

    // BR-36: upgrades apply automatically — no approval gate.
    public function applyUpgrade(string $partyId, string $ratingRole, float $delta, string $reason, ?string $relatedSaleEventId = null, ?string $eventKey = null): array
    {
        $party = $this->requireParty($partyId);
        $previousValue = (float) $party[$ratingRole];
        $newValue = $this->clamp($previousValue + abs($delta));

        $this->partyModel->setRating($partyId, $ratingRole, $newValue);
        $event = $this->ratingEventModel->createEvent([
            'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'upgrade',
            'previous_value' => $previousValue, 'new_value' => $newValue, 'reason' => $reason, 'status' => 'applied',
            'related_sale_event_id' => $relatedSaleEventId, 'event_key' => $eventKey,
        ]);

        $role = $this->roleColumnFor($ratingRole);
        if ($newValue >= self::DEFAULT_RATING) {
            $rawFlag = $role === 'buyer' ? $party['crawl_back_active_buyer'] : $party['crawl_back_active_seller'];
            $isActive = in_array($rawFlag, [true, 't', 1, '1'], true);
            if ($isActive) {
                $this->partyModel->deactivateCrawlBack($partyId, $role);
            }
        }

        return $event;
    }

    // BR-36: downgrades require approval — dual (Tenant + Super Admin) at <=2.0★.
    public function initiateDowngrade(string $partyId, string $ratingRole, float $delta, string $reason, ?string $relatedSaleEventId = null, ?string $eventKey = null): array
    {
        $party = $this->requireParty($partyId);
        $previousValue = (float) $party[$ratingRole];
        $newValue = $this->clamp($previousValue - abs($delta));
        $requiresDualApproval = $newValue <= 2.0;

        $event = $this->ratingEventModel->createEvent([
            'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'downgrade',
            'previous_value' => $previousValue, 'new_value' => $newValue, 'reason' => $reason,
            'status' => 'pending_tenant_approval',
            'related_sale_event_id' => $relatedSaleEventId, 'event_key' => $eventKey,
        ]);

        return $event + ['requiresDualApproval' => $requiresDualApproval];
    }

    // BR-35: applies a NAMED event from the graduated table above rather
    // than an arbitrary caller-supplied delta — the magnitude and
    // direction come from the table, not the call site, so every caller
    // wiring the same event applies the exact same documented figure.
    public function applyNamedEvent(string $partyId, string $ratingRole, string $eventKey, string $context = '', ?string $relatedSaleEventId = null): array
    {
        $magnitude = self::NAMED_EVENTS[$ratingRole][$eventKey] ?? null;
        if ($magnitude === null) {
            throw new \RuntimeException("BR-35: unknown named rating event '{$eventKey}' for {$ratingRole}");
        }

        $label = self::EVENT_LABELS[$eventKey] ?? $eventKey;
        $reason = "BR-35: {$label}" . ($context !== '' ? " ({$context})" : '');

        if ($magnitude === 'reset_to_1') {
            $party = $this->requireParty($partyId);
            $delta = max(0.0, (float) $party[$ratingRole] - self::PLATFORM_FLOOR);
            return $this->initiateDowngrade($partyId, $ratingRole, $delta, $reason, $relatedSaleEventId, $eventKey);
        }

        if ($magnitude > 0) {
            return $this->applyUpgrade($partyId, $ratingRole, $magnitude, $reason, $relatedSaleEventId, $eventKey);
        }

        return $this->initiateDowngrade($partyId, $ratingRole, abs($magnitude), $reason, $relatedSaleEventId, $eventKey);
    }

    // BR-36: applies only once all required approvals are present.
    public function approveDowngrade(string $eventId, string $approverPartyId, string $approverType): array
    {
        $event = $this->ratingEventModel->find($eventId);
        if (!$event) {
            throw new \RuntimeException('Rating event not found');
        }
        if ($event['status'] === 'applied') {
            throw new \RuntimeException('Rating event already applied');
        }

        $requiresDualApproval = (float) $event['new_value'] <= 2.0;

        if ($approverType === 'tenant_admin') {
            $event = $this->ratingEventModel->approveTenantAdmin($eventId, $approverPartyId);
        } elseif ($approverType === 'super_admin') {
            $event = $this->ratingEventModel->approveSuperAdmin($eventId, $approverPartyId);
        } else {
            throw new \RuntimeException("Unknown approverType: {$approverType}");
        }

        $hasTenant = !empty($event['tenant_admin_approved_at']);
        $hasSuperAdmin = !empty($event['super_admin_approved_at']);
        $readyToApply = $requiresDualApproval ? ($hasTenant && $hasSuperAdmin) : $hasTenant;

        if (!$readyToApply) {
            return ['event' => $event, 'applied' => false, 'waitingOn' => ($requiresDualApproval && !$hasSuperAdmin) ? 'super_admin' : 'tenant_admin'];
        }

        $this->partyModel->setRating($event['party_id'], $event['rating_role'], (float) $event['new_value']);
        $applied = $this->ratingEventModel->markApplied($eventId);

        $this->maybeTriggerCrawlBack($event['party_id'], $event['rating_role'], (float) $event['new_value']);

        return ['event' => $applied, 'applied' => true];
    }

    private function maybeTriggerCrawlBack(string $partyId, string $ratingRole, float $newValue): void
    {
        $role = $this->roleColumnFor($ratingRole);

        if ($newValue <= self::PLATFORM_FLOOR) {
            $this->partyModel->setShadowBanned($partyId, $role, true);
            (new AuditLogService())->log('rating.shadow_banned', $partyId, [
                'ratingRole' => $ratingRole, 'newValue' => $newValue, 'reason' => 'platform_floor',
            ]);
            return;
        }
        if ($newValue < self::SHADOW_BAN_THRESHOLD) {
            $this->partyModel->setShadowBanned($partyId, $role, true);
            (new AuditLogService())->log('rating.shadow_banned', $partyId, [
                'ratingRole' => $ratingRole, 'newValue' => $newValue, 'reason' => 'below_threshold',
            ]);
            return;
        }
        if ($newValue < self::CRAWL_BACK_THRESHOLD) {
            $offenceCount = $this->partyModel->incrementOffenceCount($partyId, $role);
            $cleanRequired = $this->cleanRequiredFor($offenceCount);
            $this->partyModel->activateCrawlBack($partyId, $role, $cleanRequired);
            (new AuditLogService())->log('rating.crawl_back_activated', $partyId, [
                'ratingRole' => $ratingRole, 'newValue' => $newValue,
                'offenceCount' => $offenceCount, 'cleanTransactionsRequired' => $cleanRequired,
            ]);
        }
    }

    // BR-35: general clean-transaction streak — every party accrues
    // this on every completed settlement regardless of Crawl-Back
    // state, distinct from BR-38's own clean-count (which only counts
    // during active rehabilitation and is handled separately below).
    // Resets after firing so it can be earned again.
    public function recordCleanStreak(string $partyId, string $ratingRole, ?string $relatedSaleEventId = null): void
    {
        $streak = $this->partyModel->incrementCleanStreak($partyId, $ratingRole);
        if ($streak >= self::SUSTAINED_CLEAN_STREAK_TARGET) {
            $this->applyNamedEvent($partyId, $ratingRole, 'sustained_clean_streak', "{$streak} consecutive clean transactions", $relatedSaleEventId);
            $this->partyModel->resetCleanStreak($partyId, $ratingRole);
        }
    }

    // BR-38: restores to exactly 3.0 once the escalated clean-count is met.
    public function recordCleanTransactionForCrawlBack(string $partyId, string $ratingRole): array
    {
        $role = $this->roleColumnFor($ratingRole);
        $party = $this->partyModel->recordCleanTransaction($partyId, $role);

        $rawFlag = $role === 'buyer' ? $party['crawl_back_active_buyer'] : $party['crawl_back_active_seller'];
        $isActive = in_array($rawFlag, [true, 't', 1, '1'], true);
        $required = $role === 'buyer' ? $party['crawl_back_clean_required_buyer'] : $party['crawl_back_clean_required_seller'];
        $completed = $role === 'buyer' ? $party['crawl_back_clean_completed_buyer'] : $party['crawl_back_clean_completed_seller'];

        if ($isActive && $completed >= $required) {
            $this->partyModel->setRating($partyId, $ratingRole, self::DEFAULT_RATING);
            $this->partyModel->deactivateCrawlBack($partyId, $role);
            $this->ratingEventModel->createEvent([
                'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'upgrade',
                'previous_value' => (float) $party[$ratingRole], 'new_value' => self::DEFAULT_RATING,
                'reason' => 'BR-38 Crawl-Back completed — restored to 3.0', 'status' => 'applied',
            ]);
            (new AuditLogService())->log('rating.crawl_back_completed', $partyId, [
                'ratingRole' => $ratingRole, 'cleanTransactionsCompleted' => $completed, 'restoredTo' => self::DEFAULT_RATING,
            ]);
            return ['crawlBackCompleted' => true, 'restoredTo' => self::DEFAULT_RATING];
        }

        return ['crawlBackCompleted' => false, 'completed' => $completed, 'required' => $required];
    }

    // BR-39: always exactly 3.0, tracks the 5-strike pattern per role.
    public function applyForcedNeutral(string $partyId, string $ratingRole, ?string $relatedSaleEventId, string $reason): array
    {
        $party = $this->requireParty($partyId);
        $previousValue = (float) $party[$ratingRole];

        $this->partyModel->setRating($partyId, $ratingRole, self::DEFAULT_RATING);
        $event = $this->ratingEventModel->createEvent([
            'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'forced_neutral',
            'previous_value' => $previousValue, 'new_value' => self::DEFAULT_RATING,
            'reason' => $reason, 'status' => 'applied', 'related_sale_event_id' => $relatedSaleEventId,
        ]);

        $strikeCount = $this->partyModel->incrementForcedNeutralCount($partyId, $ratingRole);
        if ($strikeCount >= self::FORCED_NEUTRAL_PATTERN_LIMIT) {
            // BR-35: this already matched "Stalling pattern... -0.5★"
            // exactly before this session — tagged with event_key here
            // for traceability against the same named-event table,
            // not a behavior change.
            $downgrade = $this->initiateDowngrade(
                $partyId, $ratingRole, 0.5,
                "BR-39: pattern of {$strikeCount} forced-neutral ratings triggered a rating-damaging event",
                $relatedSaleEventId, 'stalling_pattern'
            );
            return ['event' => $event, 'strikeCount' => $strikeCount, 'patternTriggered' => true, 'pendingDowngradeEvent' => $downgrade];
        }

        return ['event' => $event, 'strikeCount' => $strikeCount, 'patternTriggered' => false];
    }

    // BR-38: platform-wide defaults, used whenever a tenant hasn't set
    // its own brackets — per the project owner's explicit decision (one
    // default now, tenant-customizable later). These specific rupee
    // values are NOT specified anywhere in the BR/PR document — a
    // reasonable placeholder for salvage/surplus values, flagged here
    // for confirmation rather than treated as settled.
    private const PLATFORM_DEFAULT_LOW_BRACKET_MAX = 50000.0;
    private const PLATFORM_DEFAULT_MEDIUM_BRACKET_MAX = 500000.0;

    public function resolveLowBracketMax(array $tenant): float
    {
        return $tenant['low_bracket_max'] !== null
            ? (float) $tenant['low_bracket_max']
            : self::PLATFORM_DEFAULT_LOW_BRACKET_MAX;
    }

    // Returns null if unrestricted, or the exact value ceiling if this
    // party is currently restricted — the actual ENFORCEMENT this
    // project was missing: entry/exit tracking existed already, but
    // nothing checked it before letting a transaction through.
    public function getTransactionCeiling(string $partyId, string $ratingRole, array $tenant): ?float
    {
        $party = $this->requireParty($partyId);
        $role = $this->roleColumnFor($ratingRole);

        $rating = (float) $party[$ratingRole];

        // The 1★ platform floor is a flat, non-tenant-configurable
        // ceiling — stricter than even the Low bracket, and applies
        // regardless of Crawl-Back/flush-out state specifically.
        // Per the project owner's explicit decision, the standing-
        // deposit formula that would raise this is deliberately not
        // built — the floor is fixed until that's revisited.
        if ($rating <= self::PLATFORM_FLOOR) {
            return self::PLATFORM_FLOOR_TRANSACTION_CEILING;
        }

        $rawFlag = $role === 'buyer' ? $party['crawl_back_active_buyer'] : $party['crawl_back_active_seller'];
        $isRestricted = in_array($rawFlag, [true, 't', 1, '1'], true);
        if ($isRestricted) {
            return $this->resolveLowBracketMax($tenant);
        }

        return null;
    }

    public function isShadowBanned(string $partyId, string $ratingRole): bool
    {
        $party = $this->requireParty($partyId);
        $role = $this->roleColumnFor($ratingRole);
        return $role === 'buyer'
            ? $party['shadow_banned_at_buyer'] !== null
            : $party['shadow_banned_at_seller'] !== null;
    }

    // BR-38: "full delisting reserved strictly for confirmed fraud" —
    // the genuine end of the ladder, never automatic at any rating
    // threshold. Platform-wide (not tenant-scoped, unlike
    // SellerApplicationService::suspendSeller, D-31), and deliberately
    // gated to Super Admin only, given its severity: a Tenant Admin can
    // suspend a seller from their own tenant, but cannot end a seller's
    // ability to sell anywhere on the platform.
    public function delistSellerForFraud(string $partyId, string $superAdminId, string $confirmedFraudReason): array
    {
        $party = $this->requireParty($partyId);
        if ($party['seller_delisted_at'] !== null) {
            throw new \RuntimeException('This seller is already delisted.');
        }

        $this->partyModel->update($partyId, [
            'seller_delisted_at' => date('Y-m-d H:i:s'),
            'seller_delisted_reason' => $confirmedFraudReason,
            'seller_delisted_by_party_id' => $superAdminId,
        ]);

        // BR-35: "Confirmed fraud... Reset to 1★" — the Super Admin's
        // confirmed-fraud finding here IS the ultimate authority BR-36's
        // approval gate exists to require, so it self-approves at both
        // tiers rather than sitting pending, same pattern already
        // established in DisputeService::executeRuling for a Super
        // Admin ruling.
        $downgrade = $this->applyNamedEvent($partyId, 'seller_star_rating', 'confirmed_fraud', $confirmedFraudReason);
        $this->approveDowngrade($downgrade['id'], $superAdminId, 'tenant_admin');
        if ($downgrade['requiresDualApproval']) {
            $this->approveDowngrade($downgrade['id'], $superAdminId, 'super_admin');
        }

        // Every active listing this seller has, across every tenant, is
        // suspended — a confirmed-fraud finding is not tenant-specific.
        $db = \Config\Database::connect();
        $activeListings = $db->table('listing')
            ->where('seller_party_id', $partyId)
            ->whereIn('status', ['inventory', 'pending_approval', 'upcoming', 'active'])
            ->get()->getResultArray();

        $listingModel = new \App\Models\ListingModel();
        foreach ($activeListings as $listing) {
            $listingModel->update($listing['id'], [
                'status' => 'suspended', 'rejection_reason' => 'Seller delisted — confirmed fraud: ' . $confirmedFraudReason,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        (new AuditLogService())->log('seller.delisted', $superAdminId, [
            'delistedPartyId' => $partyId, 'reason' => $confirmedFraudReason, 'listingsSuspended' => count($activeListings),
        ]);

        return ['delisted' => true, 'listingsSuspended' => count($activeListings)];
    }

    public function isDelisted(string $partyId): bool
    {
        $party = $this->requireParty($partyId);
        return $party['seller_delisted_at'] !== null;
    }

    private function requireParty(string $partyId): array
    {
        $party = $this->partyModel->find($partyId);
        if (!$party) {
            throw new \RuntimeException('Party not found');
        }
        return $party;
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(5.0, round($value, 1)));
    }
}
