<?php

namespace App\Libraries;

use App\Models\BidModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;

class CascadeService
{
    private BidModel $bidModel;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->bidModel = new BidModel();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    // BR-28: opens H1's top-up window when a sale_event closes above Reserve.
    public function initiateCascade(string $saleEventId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }

        $ranked = $this->bidModel->findRankedBids($saleEventId, 3);
        $h1 = $ranked[0] ?? null;
        if (!$h1) {
            throw new \RuntimeException('No bids to cascade — nothing to settle');
        }

        return $this->openTopupWindow($saleEvent, $h1, 1);
    }

    private function openTopupWindow(array $saleEvent, array $bid, int $cascadeStep): array
    {
        $topupRequiredBy = EmdService::calculateTopupWindow($saleEvent['sale_format'], $cascadeStep);
        $this->bidModel->setTopupWindow($bid['id'], $topupRequiredBy->format('Y-m-d H:i:s'));
        return ['bidId' => $bid['id'], 'cascadeStep' => $cascadeStep, 'topupRequiredBy' => $topupRequiredBy];
    }

    public function processTopupPaid(string $bidId): array
    {
        $paidBid = $this->bidModel->markTopupPaid($bidId);
        $hold = $this->emdHoldModel->findBySaleEventAndParty($paidBid['sale_event_id'], $paidBid['bidder_party_id']);
        if ($hold) {
            $owed = EmdService::calculateCascadeTopupOwed((float) $hold['amount'], (float) $paidBid['amount']);
            $this->emdHoldModel->setRecalculatedAmount($hold['id'], (float) $hold['amount'] + $owed);
        }

        // BR-33: this was previously a gap — a successful top-up never
        // actually closed the sale_event or created a settlement, so
        // Easy/Express auctions had no way to reach formal closure at
        // all. Fixed as part of building the settlement flow (D-25).
        $this->saleEventModel->markClosed($paidBid['sale_event_id'], 'closed_sold');
        (new \App\Libraries\SettlementService())->createForSaleEvent(
            $paidBid['sale_event_id'], $paidBid['bidder_party_id'], (float) $paidBid['amount']
        );

        return $paidBid;
    }

    // BR-28: default → forfeit → pass baton, or full-cascade-failure at H3.
    public function processDefault(string $saleEventId, string $defaultedBidId): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $ranked = $this->bidModel->findRankedBids($saleEventId, 3);

        $defaultedIndex = null;
        foreach ($ranked as $i => $bid) {
            if ($bid['id'] === $defaultedBidId) {
                $defaultedIndex = $i;
                break;
            }
        }
        if ($defaultedIndex === null) {
            throw new \RuntimeException('Defaulted bid not found in ranked standings');
        }

        $cascadeStep = $defaultedIndex + 1;
        $this->bidModel->markDefaulted($defaultedBidId);

        $isFullCascadeFailure = $cascadeStep === 3;
        $defaultingPartyId = $ranked[$defaultedIndex]['bidder_party_id'];
        // BR-34 (D-87/D-88): the defaulting bidder's own bid amount is
        // the price they would have paid had they not defaulted — the
        // value the Success Fee bracket (BR-31) is looked up against.
        $forfeitedHold = $this->forfeitHold($saleEventId, $defaultingPartyId, (float) $ranked[$defaultedIndex]['amount'], $isFullCascadeFailure);

        // BR-35: "1st/2nd/3rd Default" — this was a genuine, previously
        // undiscovered gap: CascadeService never touched the rating
        // system at all before this. $cascadeStep maps directly onto
        // the table's tiers, and is already a real, per-sale-event
        // count (no new counter needed). Goes through the normal BR-36
        // approval gate, same as every other downgrade on this
        // platform — a Tenant/Super Admin reviews it, it doesn't apply
        // silently just because the system detected it automatically.
        $eventKey = match ($cascadeStep) {
            1 => 'default_1st', 2 => 'default_2nd', 3 => 'default_3rd',
            default => 'default_3rd',
        };
        (new RatingService())->applyNamedEvent($defaultingPartyId, 'star_rating', $eventKey, "Cascade default, sale event {$saleEventId}", $saleEventId);

        if ($isFullCascadeFailure) {
            $this->saleEventModel->markClosed($saleEventId, 'cancelled');
            return [
                'outcome' => 'full_cascade_failure',
                'cancelledSaleEventId' => $saleEventId,
                'forfeitedHold' => $forfeitedHold,
                'nextAction' => 'seller must relist via archive-and-recreate (BR-13)',
            ];
        }

        $nextBidder = $ranked[$defaultedIndex + 1] ?? null;
        if (!$nextBidder) {
            throw new \RuntimeException('BR-28 inconsistency: fewer than 3 ranked bids exist for a step < 3 default');
        }
        $this->bidModel->setStanding($nextBidder['id'], $cascadeStep === 1 ? 'h1' : 'h2');
        $window = $this->openTopupWindow($saleEvent, $nextBidder, $cascadeStep + 1);

        return [
            'outcome' => 'baton_passed',
            'newTopHolderBidId' => $nextBidder['id'],
            'newTopHolderPartyId' => $nextBidder['bidder_party_id'],
            'topupRequiredBy' => $window['topupRequiredBy'],
            'forfeitedHold' => $forfeitedHold,
        ];
    }

    private function forfeitHold(string $saleEventId, string $partyId, float $saleValue, bool $isFullCascadeFailure): ?array
    {
        $hold = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $partyId);
        if (!$hold) {
            return null;
        }
        $allocation = EmdService::calculateForfeitureAllocation((float) $hold['amount'], $saleValue, $isFullCascadeFailure);
        $result = $this->emdHoldModel->markForfeited($hold['id'], 0.0, $allocation['platformAmount'], $allocation['sellerAmount']);

        // BR-05: EMD forfeiture is real money genuinely lost by the
        // defaulting bidder — the highest-stakes financial event this
        // platform produces, always logged. actor is null: forfeiture is
        // system-triggered by a cascade default, not a human decision.
        (new \App\Libraries\AuditLogService())->log('emd.forfeited', $partyId, [
            'saleEventId' => $saleEventId, 'amount' => (float) $hold['amount'],
            'platformAmount' => $allocation['platformAmount'],
            'sellerAmount' => $allocation['sellerAmount'], 'fullCascadeFailure' => $isFullCascadeFailure,
        ]);

        return $result;
    }
}
