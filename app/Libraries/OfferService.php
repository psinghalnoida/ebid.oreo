<?php

namespace App\Libraries;

use App\Models\OfferModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;

class OfferService
{
    private OfferModel $offerModel;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->offerModel = new OfferModel();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    // BR-27: EMD gate — 10% of Expected Value, checked live on every offer.
    public function submitOffer(string $saleEventId, string $buyerPartyId, float $amount): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }
        if ($saleEvent['sale_format'] !== 'buy_now') {
            throw new \RuntimeException('OfferService is only for Buy-Now sale events');
        }
        if (!in_array($saleEvent['status'], ['active'], true)) {
            throw new \RuntimeException("Cannot submit an offer on a sale_event with status={$saleEvent['status']}");
        }

        $requiredBaseline = EmdService::calculateBaselineEmd('buy_now', (float) $saleEvent['expected_value'], null);
        $hold = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerPartyId);
        if (!$hold || $hold['status'] !== 'held' || (float) $hold['amount'] < $requiredBaseline) {
            $held = $hold['amount'] ?? 0;
            throw new \RuntimeException(
                "BR-27 violation: buyer does not have sufficient EMD held (required {$requiredBaseline}, held {$held})"
            );
        }

        return $this->offerModel->createOffer($saleEventId, $buyerPartyId, $amount);
    }

    // BR: withdrawal before acceptance requires a stated reason (a lapse
    // after 3 days, handled separately in lapseStaleOffers, does not).
    public function withdrawOffer(string $offerId, string $reason): array
    {
        $offer = $this->offerModel->find($offerId);
        if (!$offer || $offer['status'] !== 'submitted') {
            throw new \RuntimeException('Only a submitted offer can be withdrawn');
        }
        $withdrawn = $this->offerModel->markWithdrawn($offerId, $reason);
        $this->releaseHoldIfNoOtherActiveOffers($offer['sale_event_id'], $offer['buyer_party_id']);
        return $withdrawn;
    }

    // BR: offers lapse unactioned after 3 days, no reason required.
    public function lapseStaleOffers(int $olderThanDays = 3): array
    {
        $cutoff = (new \DateTimeImmutable())->modify("-{$olderThanDays} days")->format('Y-m-d H:i:s');
        $stale = $this->offerModel->findStaleSubmitted($cutoff);
        $lapsed = [];
        foreach ($stale as $offer) {
            $lapsed[] = $this->offerModel->markLapsed($offer['id']);
            $this->releaseHoldIfNoOtherActiveOffers($offer['sale_event_id'], $offer['buyer_party_id']);
        }
        return $lapsed;
    }

    // BR-42: seller's full discretion — need not be the highest offer, but
    // a reason is MANDATORY whenever it isn't.
    // BR-29: EMD adjustment — top-up owed if the accepted price is above
    // EV, excess refunded if below. Identities unlock (display-layer
    // concern, not enforced here) only once this resolves.
    public function acceptOffer(string $saleEventId, string $offerId, ?string $reason = null): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $offer = $this->offerModel->find($offerId);
        if (!$offer || $offer['sale_event_id'] !== $saleEventId || $offer['status'] !== 'submitted') {
            throw new \RuntimeException('Offer not found or not in a submitted state for this sale event');
        }

        $highest = $this->offerModel->findHighestSubmitted($saleEventId);
        $isHighest = $highest && $highest['id'] === $offerId;
        if (!$isHighest && !$reason) {
            throw new \RuntimeException(
                'BR-42 violation: a reason is required when accepting an offer other than the highest received'
            );
        }

        $accepted = $this->offerModel->markAccepted($offerId, $isHighest ? null : $reason);
        $this->offerModel->rejectAllOtherSubmitted($saleEventId, $offerId);

        // BR-29: recalculate the winning buyer's EMD against the final price
        $hold = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $offer['buyer_party_id']);
        if ($hold) {
            $adjustment = EmdService::calculateBuyNowAdjustment((float) $hold['amount'], (float) $offer['amount']);
            $this->emdHoldModel->setRecalculatedAmount($hold['id'], (float) $hold['amount'] + $adjustment);
        }

        // Release EMD for every other buyer whose offer was rejected
        foreach ($this->emdHoldModel->findAllBySaleEvent($saleEventId) as $otherHold) {
            if ($otherHold['party_id'] !== $offer['buyer_party_id']) {
                $this->emdHoldModel->markReleased($otherHold['id']);
            }
        }

        $this->saleEventModel->markClosed($saleEventId, 'closed_sold');
        $this->saleEventModel->updateCurrentPrice($saleEventId, (float) $offer['amount'], $offer['buyer_party_id']);

        return $accepted;
    }

    private function releaseHoldIfNoOtherActiveOffers(string $saleEventId, string $buyerPartyId): void
    {
        $stillActive = $this->offerModel->where('sale_event_id', $saleEventId)
            ->where('buyer_party_id', $buyerPartyId)
            ->where('status', 'submitted')
            ->countAllResults();
        if ($stillActive === 0) {
            $hold = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerPartyId);
            if ($hold && $hold['status'] === 'held') {
                $this->emdHoldModel->markReleased($hold['id']);
            }
        }
    }
}
