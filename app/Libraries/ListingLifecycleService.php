<?php

namespace App\Libraries;

use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\EmdHoldModel;

class ListingLifecycleService
{
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
    public function submitForApproval(string $listingId): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }
        if ($listing['status'] !== 'inventory') {
            throw new \RuntimeException("Cannot submit for approval from status={$listing['status']}");
        }
        return $this->listingModel->transitionStatus($listingId, 'pending_approval');
    }

    // BR-13: pending_approval -> upcoming
    public function approve(string $listingId): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'pending_approval') {
            throw new \RuntimeException('Listing must be pending_approval to approve');
        }
        return $this->listingModel->transitionStatus($listingId, 'upcoming');
    }

    // BR-13: every rejection requires a closed-list reason, logged.
    // Rejected listings return to inventory so the seller can revise and resubmit.
    public function reject(string $listingId, string $reason): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'pending_approval') {
            throw new \RuntimeException('Listing must be pending_approval to reject');
        }
        return $this->listingModel->transitionStatus($listingId, 'inventory', $reason);
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
        $this->cancelOpenSaleEventsForListing($listingId, 'BR-13 material edit — listing superseded');

        $result = $this->listingModel->supersede($listingId, $newListingData + [
            'tenant_id' => $listing['tenant_id'],
            'seller_party_id' => $listing['seller_party_id'],
        ]);

        // Per BR-13: re-enters at UPCOMING (the edit request's own approval
        // already happened before this call).
        $this->listingModel->transitionStatus($result['newListing']['id'], 'upcoming');
        $result['newListing'] = $this->listingModel->findActiveById($result['newListing']['id']);

        return $result;
    }

    // BR-14: Tenant Admin/Super Admin emergency stop — any format, any time,
    // mandatory audited reason. Cancels the event, refunds all EMD.
    public function emergencyStop(string $saleEventId, string $reason): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }

        $this->bidModel->withdrawAllForSaleEvent($saleEventId);
        $this->releaseAllHoldsForSaleEvent($saleEventId);

        $this->saleEventModel->update($saleEventId, [
            'status' => 'cancelled',
            'emergency_stopped_at' => date('Y-m-d H:i:s'),
            'emergency_stop_reason' => $reason,
            'actual_closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

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

    private function cancelOpenSaleEventsForListing(string $listingId, string $reason): void
    {
        $db = \Config\Database::connect();
        $openEvents = $db->table('sale_event')
            ->where('listing_id', $listingId)
            ->whereIn('status', ['pending_approval', 'grace_period', 'active'])
            ->get()->getResultArray();

        foreach ($openEvents as $event) {
            $this->emergencyStop($event['id'], $reason);
        }
    }

    private function releaseAllHoldsForSaleEvent(string $saleEventId): void
    {
        foreach ($this->emdHoldModel->findAllBySaleEvent($saleEventId) as $hold) {
            $this->emdHoldModel->markReleased($hold['id']);
        }
    }
}
