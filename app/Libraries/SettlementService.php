<?php

namespace App\Libraries;

use App\Models\SettlementModel;
use App\Models\SaleEventModel;
use App\Models\TenantModel;
use App\Models\EmdHoldModel;
use App\Models\ListingModel;

class SettlementService
{
    // BR-39: forced-neutral triggers once a settlement has sat incomplete
    // this many days. Not explicitly quantified in the retrieved BR/PR
    // text — a reasonable default, flagged the same way the OTP-attempt
    // limit was in AuthService, not treated as a settled business rule.
    private const STALL_THRESHOLD_DAYS = 7;

    private SettlementModel $settlementModel;
    private SaleEventModel $saleEventModel;
    private TenantModel $tenantModel;
    private EmdHoldModel $emdHoldModel;
    private ListingModel $listingModel;
    private RatingService $ratingService;

    public function __construct()
    {
        $this->settlementModel = new SettlementModel();
        $this->saleEventModel = new SaleEventModel();
        $this->tenantModel = new TenantModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->listingModel = new ListingModel();
        $this->ratingService = new RatingService();
    }

    // Called once a sale_event has a confirmed winner (Buy-Now acceptance,
    // or a successful cascade top-up on Easy/Express).
    public function createForSaleEvent(string $saleEventId, string $buyerId, float $finalPrice): array
    {
        $existing = $this->settlementModel->findBySaleEvent($saleEventId);
        if ($existing) {
            return $existing;
        }
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        return $this->settlementModel->createSettlement($saleEventId, $buyerId, $listing['seller_party_id'], $finalPrice);
    }

    public function confirmSellerNoc(string $settlementId, string $callerId): array
    {
        $settlement = $this->requireSettlement($settlementId);
        if ($settlement['seller_party_id'] !== $callerId) {
            throw new \RuntimeException('BR-33: only the seller may confirm receipt of payment.');
        }
        $this->settlementModel->update($settlementId, ['seller_noc_confirmed_at' => date('Y-m-d H:i:s')]);
        return $this->checkCompletion($settlementId);
    }

    public function confirmBuyerNoc(string $settlementId, string $callerId): array
    {
        $settlement = $this->requireSettlement($settlementId);
        if ($settlement['buyer_party_id'] !== $callerId) {
            throw new \RuntimeException('BR-33: only the buyer may confirm receipt of goods.');
        }
        $this->settlementModel->update($settlementId, ['buyer_noc_confirmed_at' => date('Y-m-d H:i:s')]);
        return $this->checkCompletion($settlementId);
    }

    // BR-33: mandatory rating in both directions. outcome 'good' applies
    // an automatic upgrade (BR-36: upgrades need no approval). outcome
    // 'problem' initiates a downgrade through the EXISTING BR-36
    // approval-gated flow — it does not apply immediately, consistent
    // with how every other downgrade in this codebase works.
    public function submitRating(string $settlementId, string $callerId, string $raterRole, string $outcome, ?string $reason = null): array
    {
        $settlement = $this->requireSettlement($settlementId);

        if ($raterRole === 'buyer') {
            if ($settlement['buyer_party_id'] !== $callerId) {
                throw new \RuntimeException('BR-33: only the buyer may rate the seller on this settlement.');
            }
            $rateeId = $settlement['seller_party_id'];
            $ratingRole = 'seller_star_rating';
            $timestampField = 'buyer_rated_seller_at';
        } elseif ($raterRole === 'seller') {
            if ($settlement['seller_party_id'] !== $callerId) {
                throw new \RuntimeException('BR-33: only the seller may rate the buyer on this settlement.');
            }
            $rateeId = $settlement['buyer_party_id'];
            $ratingRole = 'star_rating';
            $timestampField = 'seller_rated_buyer_at';
        } else {
            throw new \RuntimeException("Unknown raterRole: {$raterRole}");
        }

        if ($outcome === 'good') {
            $this->ratingService->applyUpgrade($rateeId, $ratingRole, 0.1, "Positive settlement rating (settlement {$settlementId})");
        } elseif ($outcome === 'problem') {
            if (!$reason) {
                throw new \RuntimeException('A reason is required when reporting a settlement problem.');
            }
            $this->ratingService->initiateDowngrade($rateeId, $ratingRole, 0.3, $reason);
        } else {
            throw new \RuntimeException("Unknown outcome: {$outcome}");
        }

        $this->settlementModel->update($settlementId, [$timestampField => date('Y-m-d H:i:s')]);
        return $this->checkCompletion($settlementId);
    }

    // BR-33: formal closure + fee deduction, once all four steps are done.
    private function checkCompletion(string $settlementId): array
    {
        $settlement = $this->settlementModel->find($settlementId);
        $allDone = $settlement['seller_noc_confirmed_at'] && $settlement['buyer_noc_confirmed_at']
            && $settlement['buyer_rated_seller_at'] && $settlement['seller_rated_buyer_at'];

        if ($allDone && $settlement['status'] !== 'completed') {
            $saleEvent = $this->saleEventModel->find($settlement['sale_event_id']);
            $tenant = $this->tenantModel->find($saleEvent['tenant_id']);
            $hold = $this->emdHoldModel->findBySaleEventAndParty($settlement['sale_event_id'], $settlement['buyer_party_id']);

            if ($hold && $hold['status'] === 'held') {
                $fees = EmdService::calculateSettlementFee(
                    (float) $settlement['final_price'], (float) $tenant['buyer_fee_percent'], (float) $hold['amount']
                );
                $this->emdHoldModel->markSettled($hold['id'], $fees['tenantAmount'], $fees['saasAmount'], $fees['buyerRefund']);
            }

            $this->settlementModel->update($settlementId, ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
        }

        return $this->settlementModel->find($settlementId);
    }

    // BR-39: flags settlements stalled past the threshold. Callable now;
    // wiring this to run automatically on a real schedule is Tier 2's
    // scheduled-job infrastructure item, not yet built (D-23).
    public function flagStalledSettlements(): array
    {
        $cutoff = (new \DateTimeImmutable())->modify('-' . self::STALL_THRESHOLD_DAYS . ' days')->format('Y-m-d H:i:s');
        $candidates = $this->settlementModel->findStalledCandidates($cutoff);
        $flagged = [];
        foreach ($candidates as $settlement) {
            $this->settlementModel->update($settlement['id'], [
                'status' => 'stalled', 'stall_flagged_at' => date('Y-m-d H:i:s'),
            ]);
            $flagged[] = $settlement['id'];
        }
        return $flagged;
    }

    // BR-39: administrative force-completion of a stalled settlement —
    // applies forced-neutral (exactly 3.0) ratings for whichever side(s)
    // never rated, and force-confirms whichever NOC(s) never came in, so
    // the transaction can formally close and EMD can be released rather
    // than remaining stuck indefinitely.
    public function forceResolveStalled(string $settlementId): array
    {
        $settlement = $this->requireSettlement($settlementId);
        if ($settlement['status'] !== 'stalled') {
            throw new \RuntimeException('Only a stalled settlement can be force-resolved.');
        }

        if (!$settlement['buyer_rated_seller_at']) {
            $this->ratingService->applyForcedNeutral(
                $settlement['seller_party_id'], 'seller_star_rating', $settlement['sale_event_id'],
                'BR-39: buyer never rated — stall resolution forced-neutral'
            );
            $this->settlementModel->update($settlementId, ['buyer_rated_seller_at' => date('Y-m-d H:i:s')]);
        }
        if (!$settlement['seller_rated_buyer_at']) {
            $this->ratingService->applyForcedNeutral(
                $settlement['buyer_party_id'], 'star_rating', $settlement['sale_event_id'],
                'BR-39: seller never rated — stall resolution forced-neutral'
            );
            $this->settlementModel->update($settlementId, ['seller_rated_buyer_at' => date('Y-m-d H:i:s')]);
        }
        if (!$settlement['seller_noc_confirmed_at']) {
            $this->settlementModel->update($settlementId, ['seller_noc_confirmed_at' => date('Y-m-d H:i:s')]);
        }
        if (!$settlement['buyer_noc_confirmed_at']) {
            $this->settlementModel->update($settlementId, ['buyer_noc_confirmed_at' => date('Y-m-d H:i:s')]);
        }

        $this->settlementModel->update($settlementId, ['forced_neutral_applied_at' => date('Y-m-d H:i:s')]);
        return $this->checkCompletion($settlementId);
    }

    private function requireSettlement(string $settlementId): array
    {
        $settlement = $this->settlementModel->find($settlementId);
        if (!$settlement) {
            throw new \RuntimeException('Settlement not found');
        }
        return $settlement;
    }
}
