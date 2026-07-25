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
    public function applyUpgrade(string $partyId, string $ratingRole, float $delta, string $reason): array
    {
        $party = $this->requireParty($partyId);
        $previousValue = (float) $party[$ratingRole];
        $newValue = $this->clamp($previousValue + abs($delta));

        $this->partyModel->setRating($partyId, $ratingRole, $newValue);
        $event = $this->ratingEventModel->createEvent([
            'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'upgrade',
            'previous_value' => $previousValue, 'new_value' => $newValue, 'reason' => $reason, 'status' => 'applied',
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
    public function initiateDowngrade(string $partyId, string $ratingRole, float $delta, string $reason): array
    {
        $party = $this->requireParty($partyId);
        $previousValue = (float) $party[$ratingRole];
        $newValue = $this->clamp($previousValue - abs($delta));
        $requiresDualApproval = $newValue <= 2.0;

        $event = $this->ratingEventModel->createEvent([
            'party_id' => $partyId, 'rating_role' => $ratingRole, 'event_type' => 'downgrade',
            'previous_value' => $previousValue, 'new_value' => $newValue, 'reason' => $reason,
            'status' => 'pending_tenant_approval',
        ]);

        return $event + ['requiresDualApproval' => $requiresDualApproval];
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
            $downgrade = $this->initiateDowngrade(
                $partyId, $ratingRole, 0.5,
                "BR-39: pattern of {$strikeCount} forced-neutral ratings triggered a rating-damaging event"
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
