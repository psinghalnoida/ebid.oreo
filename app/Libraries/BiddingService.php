<?php

namespace App\Libraries;

use App\Models\BidModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;

class BiddingService
{
    private const BID_CEILING_MULTIPLIER = 1.5; // BR-43

    private BidModel $bidModel;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->bidModel = new BidModel();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    // BR-27: live EMD gate check on every single bid.
    // BR-43: 150% ceiling anti-jacking check.
    public function placeBid(string $saleEventId, string $bidderPartyId, float $amount): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }

        // BR-21/BR-22: conflict-of-interest block
        $conflict = (new AuthorizationService())->hasConflictOfInterest($bidderPartyId, $saleEvent['listing_id']);
        if ($conflict) {
            throw new \RuntimeException($conflict);
        }

        if ($saleEvent['status'] !== 'active') {
            throw new \RuntimeException("Cannot bid on a sale_event with status={$saleEvent['status']}");
        }

        $requiredBaseline = EmdService::calculateBaselineEmd(
            $saleEvent['sale_format'],
            $saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null,
            $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value'] : null
        );

        $hold = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $bidderPartyId);
        if (!$hold || $hold['status'] !== 'held' || (float) $hold['amount'] < $requiredBaseline) {
            $held = $hold['amount'] ?? 0;
            throw new \RuntimeException(
                "BR-27 violation: bidder does not have sufficient EMD held (required {$requiredBaseline}, held {$held})"
            );
        }

        $currentHigh = $this->bidModel->findCurrentHighBid($saleEventId);
        $currentHighAmount = $currentHigh ? (float) $currentHigh['amount'] : (float) ($saleEvent['reserve_value'] ?? 0);
        if ($currentHighAmount > 0 && $amount > $currentHighAmount * self::BID_CEILING_MULTIPLIER) {
            $ceiling = round($currentHighAmount * self::BID_CEILING_MULTIPLIER, 2);
            throw new \RuntimeException(
                "BR-43 violation: bid {$amount} exceeds 150% of current high bid {$currentHighAmount} (ceiling {$ceiling})"
            );
        }

        if ($currentHigh && $amount <= $currentHighAmount) {
            throw new \RuntimeException("Bid {$amount} does not exceed current high bid {$currentHighAmount}");
        }

        $newBid = $this->bidModel->createBid($saleEventId, $bidderPartyId, $amount);
        $this->bidModel->setStanding($newBid['id'], 'h1');
        $this->bidModel->resetOutbidStandings($saleEventId, [$newBid['id']]);
        $this->saleEventModel->updateCurrentPrice($saleEventId, $amount, $bidderPartyId);

        $ranked = $this->bidModel->findRankedBids($saleEventId, 3);
        $standingsByRank = ['h1', 'h2', 'h3'];
        foreach ($ranked as $i => $bid) {
            if ($bid['id'] !== $newBid['id']) {
                $this->bidModel->setStanding($bid['id'], $standingsByRank[$i]);
            }
        }

        // Return the up-to-date record — the pre-update snapshot captured
        // right after insert() would still show the default 'outbid'
        // standing, not the 'h1' just set above.
        return $this->bidModel->find($newBid['id']);
    }
}
