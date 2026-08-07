<?php

namespace App\Libraries;

use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\EmdHoldModel;

class ListingLifecycleService
{
    // PR-09 step 8: "the Tenant Admin selects a reason from a closed
    // list ... may append free-text detail." Previously the reject
    // route had no reason field at all and silently hardcoded
    // 'insufficient photos' on every rejection, regardless of the
    // actual reason — a real, incorrect audit trail, not just a missing
    // feature.
    public const REJECTION_REASONS = [
        'insufficient_photos' => 'Insufficient photos',
        'suspected_fraudulent_images' => 'Suspected fraudulent images',
        'mismatched_description' => 'Mismatched description',
        'incomplete_metadata' => 'Incomplete metadata',
    ];

    // BR-07: "Permitted categories: Salvaged Claims Goods, Second-Hand/
    // Used Goods, Abandoned Goods, Antiques, Repossessed Banking Assets,
    // Industrial/Commercial Surplus, Custom/Confiscated Goods, and
    // Lost-and-Found inventories" — the platform's own closed list,
    // explicitly excluding new retail-consumer goods. Was previously a
    // free-text field with no enforcement at all.
    public const PERMITTED_CATEGORIES = [
        'Salvaged Claims Goods',
        'Second-Hand/Used Goods',
        'Abandoned Goods',
        'Antiques',
        'Repossessed Banking Assets',
        'Industrial/Commercial Surplus',
        'Custom/Confiscated Goods',
        'Lost-and-Found Inventories',
    ];

    private ListingModel $listingModel;
    private SaleEventModel $saleEventModel;
    private BidModel $bidModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->listingModel = new ListingModel();
        $this->saleEventModel = new SaleEventModel();
        $this->bidModel = new BidModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    // BR-13: inventory -> pending_approval
    // BR-11: minimum 5, maximum 50 photos required before a listing can
    // be submitted for Tenant Admin review.
    public function submitForApproval(string $listingId): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }
        if ($listing['status'] !== 'inventory') {
            throw new \RuntimeException("Cannot submit for approval from status={$listing['status']}");
        }
        if ((int) $listing['media_count'] < 5) {
            throw new \RuntimeException(
                "BR-11 violation: at least 5 photos are required before submitting for approval (currently {$listing['media_count']})"
            );
        }
        // PR-09 step 6: "if fewer than 5 photos or no Main Display Photo,
        // submission is blocked" — the no-primary half of this was never
        // actually checked. It happened to be unreachable today only
        // because the first uploaded photo is always auto-marked primary
        // and there is no media-delete feature — an unenforced
        // coincidence, not a real validation rule, so made explicit here.
        $hasPrimaryPhoto = (new \App\Models\ListingMediaModel())
            ->where('listing_id', $listingId)->where('media_type', 'photo')->where('is_primary', true)
            ->countAllResults() > 0;
        if (!$hasPrimaryPhoto) {
            throw new \RuntimeException('BR-11 violation: a Main Display Photo must be designated before submitting for approval.');
        }
        return $this->listingModel->transitionStatus($listingId, 'pending_approval');
    }

    // BR-13: pending_approval -> upcoming
    public function approve(string $listingId, ?string $actorPartyId = null): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'pending_approval') {
            throw new \RuntimeException('Listing must be pending_approval to approve');
        }
        $result = $this->listingModel->transitionStatus($listingId, 'upcoming');
        (new \App\Libraries\AuditLogService())->log('listing.approved', $actorPartyId, ['listingId' => $listingId]);

        // PR-37: "On approval, a listing.approved webhook fires; listing
        // status is UPCOMING."
        (new \App\Libraries\TenantWebhookService())->fire($listing['tenant_id'], 'listing.approved', [
            'listingId' => $listingId, 'status' => 'upcoming',
        ]);

        return $result;
    }

    // BR-13/PR-09: every rejection requires a genuine closed-list
    // reason, logged, with optional free-text detail appended — not any
    // arbitrary string.
    public function reject(string $listingId, string $reasonKey, ?string $detail = null, ?string $actorPartyId = null): array
    {
        if (!array_key_exists($reasonKey, self::REJECTION_REASONS)) {
            throw new \RuntimeException('Rejection reason must be one of the closed list: ' . implode(', ', array_keys(self::REJECTION_REASONS)));
        }
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'pending_approval') {
            throw new \RuntimeException('Listing must be pending_approval to reject');
        }

        $reasonLabel = self::REJECTION_REASONS[$reasonKey];
        $storedReason = $detail ? "{$reasonLabel}: {$detail}" : $reasonLabel;

        $result = $this->listingModel->transitionStatus($listingId, 'inventory', $storedReason);
        (new \App\Libraries\AuditLogService())->log('listing.rejected', $actorPartyId, [
            'listingId' => $listingId, 'reasonKey' => $reasonKey, 'reason' => $storedReason,
        ]);

        // BR-61: "rejected auctions" is one of Standing Review's
        // explicit complaint sources.
        (new \App\Libraries\StandingReviewService())->recordComplaint($listing['seller_party_id'], "Listing rejected: {$storedReason}");

        return $result;
    }

    // BR-13: material edit on an ACTIVE listing — archive-and-recreate.
    // "Re-entering the lifecycle from UPCOMING" per BR-13, since the edit
    // request itself already went through Tenant Admin approval before
    // this is called.
    public function requestMaterialEdit(string $listingId, array $newListingData): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }
        if ($listing['status'] !== 'active') {
            throw new \RuntimeException('Material edit via archive-and-recreate only applies to ACTIVE listings');
        }

        // BR-14: any active sale_event on this listing is cancelled;
        // all bids withdrawn, all EMD released — never silently migrated.
        $this->cancelOpenSaleEventsForListing($listingId, 'BR-13 material edit — listing superseded', $listing['seller_party_id']);

        $result = $this->listingModel->supersede($listingId, $newListingData + [
            'tenant_id' => $listing['tenant_id'],
            'seller_party_id' => $listing['seller_party_id'],
        ]);

        // Per BR-13: re-enters at UPCOMING (the edit request's own approval
        // already happened before this call).
        $this->listingModel->transitionStatus($result['newListing']['id'], 'upcoming');
        $result['newListing'] = $this->listingModel->findActiveById($result['newListing']['id']);

        // PR-37: "a listing.archived webhook fires on the old ID, carrying
        // a supersededBy reference to the new ID."
        (new \App\Libraries\TenantWebhookService())->fire($listing['tenant_id'], 'listing.archived', [
            'listingId' => $listingId, 'supersededBy' => $result['newListing']['id'],
        ]);

        return $result;
    }

    // BR-14: Tenant Admin/Super Admin emergency stop — any format, any time,
    // mandatory audited reason. Cancels the event, refunds all EMD.
    public function emergencyStop(string $saleEventId, string $reason, ?string $actorPartyId = null): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }

        $this->bidModel->withdrawAllForSaleEvent($saleEventId);
        $releasedPartyIds = $this->releaseAllHoldsForSaleEvent($saleEventId);

        (new \App\Libraries\AuditLogService())->log('sale_event.emergency_stopped', $actorPartyId, [
            'saleEventId' => $saleEventId, 'reason' => $reason, 'holdsReleased' => count($releasedPartyIds),
        ]);

        $this->saleEventModel->update($saleEventId, [
            'status' => 'cancelled',
            'emergency_stopped_at' => date('Y-m-d H:i:s'),
            'emergency_stop_reason' => $reason,
            'actual_closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // D-114: last item in the WebSocket retrofit's original
        // real-time-coverage sweep (D-108 through D-113 covered
        // bids/offers/settlement/dispute/rating/cascade). Two
        // audiences, same amount/reason-free-public-vs-private-nudge
        // split every prior decision has used: the sale_event room
        // (any visitor on this listing page) only learns the auction
        // was stopped — the reason isn't shown anywhere in the UI
        // today even to a logged-in visitor, so it isn't broadcast
        // either, consistent with not exposing something the
        // synchronous page itself doesn't reveal. Each bidder whose
        // EMD was actually just released gets a private, actionable
        // nudge on their own already-open party channel.
        $broadcaster = new RealtimeBroadcastService();
        $broadcaster->broadcast($saleEventId, 'sale_event_emergency_stopped', []);
        foreach ($releasedPartyIds as $partyId) {
            $broadcaster->broadcastToBuyer($partyId, 'emd_released', [
                'saleEventId' => $saleEventId, 'reason' => 'emergency_stop',
            ]);
        }

        return $this->saleEventModel->find($saleEventId);
    }

    // BR-14: Easy/Buy-Now get a 60-minute post-approval grace window;
    // Express gets none (fully locked); Tender has no fixed window at all.
    public function approveSaleEvent(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['status'] !== 'pending_approval') {
            throw new \RuntimeException('Sale event must be pending_approval to approve');
        }

        // BR-57: Express has no inspection window, so the seller's
        // defect disclosure is the only accountability mechanism
        // available — approval is blocked entirely without it.
        if ($saleEvent['sale_format'] === 'express' && $saleEvent['defect_disclosure_completed_at'] === null) {
            throw new \RuntimeException('BR-57: this Express Auction cannot be approved until the seller completes the mandatory defect disclosure checklist.');
        }

        if (in_array($saleEvent['sale_format'], ['easy', 'buy_now'], true)) {
            $graceEndsAt = (new \DateTimeImmutable())->modify('+60 minutes');
            $this->saleEventModel->update($saleEventId, [
                'status' => 'grace_period',
                'grace_period_ends_at' => $graceEndsAt->format('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            // Express: fully locked, no grace window. Tender: no fixed
            // window (seller's own discretion) — both go straight to active.
            $this->saleEventModel->transitionStatus($saleEventId, 'active');
        }

        // PR-37: "on approval, a sale_event.created webhook fires."
        (new \App\Libraries\TenantWebhookService())->fire($saleEvent['tenant_id'], 'sale_event.created', [
            'saleEventId' => $saleEventId, 'listingId' => $saleEvent['listing_id'], 'saleFormat' => $saleEvent['sale_format'],
        ]);

        // D-115: the domain-event equivalent of the tenant webhook just
        // above — same milestone, different audience. The webhook
        // notifies an external Tenant integration; this notifies
        // in-process/future consumers (analytics, AI, a real
        // notification queue) with zero coupling to this service.
        \CodeIgniter\Events\Events::trigger(\App\Libraries\DomainEvents::AUCTION_CREATED, [
            'saleEventId' => $saleEventId, 'listingId' => $saleEvent['listing_id'],
            'tenantId' => $saleEvent['tenant_id'], 'saleFormat' => $saleEvent['sale_format'],
        ]);

        return $this->saleEventModel->find($saleEventId);
    }

    // BR-14: direct edit within the 60-minute grace window resets the clock
    // (per PR-20). Only valid for Easy/Buy-Now, only while grace is open.
    public function editWithinGrace(string $saleEventId, array $changes): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['status'] !== 'grace_period') {
            throw new \RuntimeException('Sale event is not within an active grace period');
        }
        if (!in_array($saleEvent['sale_format'], ['easy', 'buy_now'], true)) {
            throw new \RuntimeException('Only Easy/Buy-Now support the grace-period edit window');
        }
        $now = new \DateTimeImmutable();
        $graceEnds = new \DateTimeImmutable($saleEvent['grace_period_ends_at']);
        if ($now > $graceEnds) {
            throw new \RuntimeException('Grace period has already lapsed — parameters are frozen');
        }

        // Any bids placed during grace (shouldn't normally happen pre-active,
        // but defensively withdrawn per BR-14's "never silently migrated" rule)
        $this->bidModel->withdrawAllForSaleEvent($saleEventId);

        $newGraceEndsAt = $now->modify('+60 minutes');
        $changes['grace_period_ends_at'] = $newGraceEndsAt->format('Y-m-d H:i:s');
        $changes['updated_at'] = date('Y-m-d H:i:s');
        $this->saleEventModel->update($saleEventId, $changes);

        return $this->saleEventModel->find($saleEventId);
    }

    // BR-14: at 60 minutes with no edits, parameters freeze and the event
    // transitions to active. Called by a scheduled job in production —
    // exposed here as an explicit method for the same reason.
    public function freezeAfterGrace(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent || $saleEvent['status'] !== 'grace_period') {
            throw new \RuntimeException('Sale event is not in grace_period');
        }
        $graceEnds = new \DateTimeImmutable($saleEvent['grace_period_ends_at']);
        if (new \DateTimeImmutable() < $graceEnds) {
            throw new \RuntimeException('Grace period has not yet lapsed');
        }
        return $this->saleEventModel->transitionStatus($saleEventId, 'active');
    }

    private function cancelOpenSaleEventsForListing(string $listingId, string $reason, ?string $actorPartyId = null): void
    {
        $db = \Config\Database::connect();
        $openEvents = $db->table('sale_event')
            ->where('listing_id', $listingId)
            ->whereIn('status', ['pending_approval', 'grace_period', 'active'])
            ->get()->getResultArray();

        foreach ($openEvents as $event) {
            $this->emergencyStop($event['id'], $reason, $actorPartyId);
        }
    }

    // D-114: returns the released holds' own party IDs, not just a
    // count — emergencyStop() needs them to privately notify each
    // affected bidder, not just log a number.
    private function releaseAllHoldsForSaleEvent(string $saleEventId): array
    {
        $releasedPartyIds = [];
        $payoutControl = new \App\Libraries\PayoutControlService();
        foreach ($this->emdHoldModel->findAllBySaleEvent($saleEventId) as $hold) {
            if ($payoutControl->guardedRelease($hold['id'])) {
                $releasedPartyIds[] = $hold['party_id'];
            }
        }
        return $releasedPartyIds;
    }
}
