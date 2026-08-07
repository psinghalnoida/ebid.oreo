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

    // BR-49: explicitly stated in the document itself, not a placeholder
    // — "a single ₹10 Lakh threshold applies uniformly across all
    // tenants and sale formats — no tenant-specific carve-outs."
    // PR-04/D-75: now the Super Admin's live "BR-49.high_value_threshold"
    // rule — the same rule PayoutControlService reads for its own
    // high-value payout review gate, so editing it once genuinely
    // affects both. This constant is only the fallback default.
    private const HIGH_VALUE_DISPOSAL_THRESHOLD_DEFAULT = 1000000.0;

    // BR-53: the BR text itself leaves the rate open pending tax-advisor
    // confirmation — the Super Admin (project owner) has confirmed 10%
    // for this platform (Section 194-O), so this is a real constant now,
    // not a DEV-ONLY placeholder.
    private const TDS_RATE_PERCENT = 10.0;

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
        (new AuditLogService())->log('settlement.seller_noc_confirmed', $callerId, ['settlementId' => $settlementId]);
        return $this->checkCompletion($settlementId);
    }

    public function confirmBuyerNoc(string $settlementId, string $callerId): array
    {
        $settlement = $this->requireSettlement($settlementId);
        if ($settlement['buyer_party_id'] !== $callerId) {
            throw new \RuntimeException('BR-33: only the buyer may confirm receipt of goods.');
        }
        $this->settlementModel->update($settlementId, ['buyer_noc_confirmed_at' => date('Y-m-d H:i:s')]);
        (new AuditLogService())->log('settlement.buyer_noc_confirmed', $callerId, ['settlementId' => $settlementId]);
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
            // BR-36: threading the sale event through means this pending
            // downgrade is finally reachable by a real Tenant Admin —
            // previously it had no related_sale_event_id at all, so
            // nothing could ever resolve which tenant's admin should
            // review it (a genuine, pre-existing gap, not introduced
            // here — found while wiring BR-35's own review queue).
            $this->ratingService->initiateDowngrade($rateeId, $ratingRole, 0.3, $reason, $settlement['sale_event_id']);
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

            // BR-31/32/33 (D-87/D-88): the Success Fee is a fixed,
            // platform-wide schedule — the Tenant Admin no longer sets or
            // overrides any fee rate. What DOES vary per Trading Session
            // is the Fee Payer Election (BR-32): Buyer-Pays deducts the
            // fee from the buyer's held EMD as before; Seller-Pays
            // releases the buyer's EMD in full and instead bills the
            // Tenant monthly (TenantBillingService), since the platform
            // never touches the seller's own 100% sale-value proceeds
            // (BR-33) to deduct from directly.
            $feeWasSettled = false;
            $feeAmount = 0.0;
            if ($hold && $hold['status'] === 'held') {
                if ($saleEvent['fee_payer'] === 'seller_pays') {
                    $feeAmount = EmdService::calculateSuccessFee((float) $settlement['final_price']);
                    // BR-50: the same high-value review gate that guards
                    // a plain EMD release also guards this one.
                    $feeWasSettled = (new PayoutControlService())->guardedRelease($hold['id']);
                    if ($feeWasSettled) {
                        (new TenantBillingService())->recordUnbilledFee(
                            $tenant['id'], $settlementId, $settlement['sale_event_id'], $feeAmount
                        );
                    }
                } else {
                    $fees = EmdService::calculateSettlementFee((float) $settlement['final_price'], (float) $hold['amount']);
                    $feeAmount = $fees['saasAmount'];
                    // BR-50: a high-value refund to a recently-changed bank
                    // account is deferred to Tenant/SaaS Admin review instead
                    // of settling immediately — $feeWasSettled correctly
                    // reflects that, so the invoice below isn't generated
                    // for a fee that hasn't actually been deducted yet.
                    $feeWasSettled = (new PayoutControlService())->guardedSettle(
                        $hold['id'], 0.0, $fees['saasAmount'], $fees['buyerRefund']
                    );
                }
            }

            // BR-53: TDS under Section 194-O, on the GROSS sale amount —
            // applies to every completed facilitated sale regardless of
            // format (unlike BR-56's invoice, BR-53's own text carries no
            // Tender carve-out). Deducted from what the platform owes the
            // seller; distinct from, and on top of, the buyer-side
            // commission split above.
            $tdsAmount = round((float) $settlement['final_price'] * (self::TDS_RATE_PERCENT / 100), 2);

            $this->settlementModel->update($settlementId, [
                'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
                'tds_rate_percent' => self::TDS_RATE_PERCENT, 'tds_amount' => $tdsAmount,
            ]);

            (new AuditLogService())->log('settlement.tds_deducted', $settlement['seller_party_id'], [
                'settlementId' => $settlementId, 'grossAmount' => (float) $settlement['final_price'],
                'tdsRatePercent' => self::TDS_RATE_PERCENT, 'tdsAmount' => $tdsAmount,
            ]);

            // PR-37: "Sale closure, settlement completion, and
            // dispute-filed events each fire their own webhook, scoped to
            // BR-63 visibility."
            (new TenantWebhookService())->fire($saleEvent['tenant_id'], 'settlement.completed', [
                'settlementId' => $settlementId, 'saleEventId' => $settlement['sale_event_id'],
                'finalPrice' => (float) $settlement['final_price'],
            ]);

            // BR-38: a completed settlement is a genuine clean
            // transaction for BOTH parties — the exit path was fully
            // built (RatingService::recordCleanTransactionForCrawlBack)
            // but never actually called anywhere until now.
            $ratingService = new RatingService();
            $ratingService->recordCleanTransactionForCrawlBack($settlement['buyer_party_id'], 'star_rating');
            $ratingService->recordCleanTransactionForCrawlBack($settlement['seller_party_id'], 'seller_star_rating');

            // BR-35: "Sustained clean streak" — a general reward
            // distinct from BR-38's crawl-back-specific clean count
            // above; every party accrues this regardless of Crawl-Back
            // state.
            $ratingService->recordCleanStreak($settlement['buyer_party_id'], 'star_rating', $settlement['sale_event_id']);
            $ratingService->recordCleanStreak($settlement['seller_party_id'], 'seller_star_rating', $settlement['sale_event_id']);

            // BR-49: deterministic, non-discretionary — no manual
            // trigger, no tenant carve-outs, a single ₹10L threshold
            // applied uniformly.
            $this->maybeRecordHighValueDisposal($settlementId, $settlement);

            // BR-56: automatic on Buy-Now, Express, Easy — explicitly
            // excluded on Tender, which follows the seller's own custom
            // terms instead. Issued to whichever party paid the Success
            // Fee under this session's election (BR-56's own text).
            if ($feeWasSettled && $saleEvent['sale_format'] !== 'tender') {
                (new InvoiceService())->generateForSettlement(
                    $settlementId, $settlement, $feeAmount, $saleEvent['fee_payer']
                );
            }

            // Section 7.10 (ADWITIX_Master.docx): a Trading Session
            // Chronicle is generated for every completed settlement,
            // regardless of format or fee payer -- unlike the invoice
            // above, nothing in Section 7.10 carves Tender out.
            $completedSettlement = $this->settlementModel->find($settlementId);
            (new ChronicleService())->generate($settlementId, $completedSettlement, $feeAmount, $tdsAmount);

            // D-115: fired only on the genuine completion transition
            // (this whole block is gated on it), not on every
            // checkCompletion() call the way the D-109 WS broadcast
            // below is — a domain event should mean the milestone
            // actually happened, not "something about this settlement
            // changed." Stands in for the Chief Architect directive's
            // own "PaymentReceived" example (see DomainEvents::SETTLEMENT_COMPLETED).
            \CodeIgniter\Events\Events::trigger(\App\Libraries\DomainEvents::SETTLEMENT_COMPLETED, [
                'settlementId' => $settlementId, 'saleEventId' => $settlement['sale_event_id'],
                'finalPrice' => (float) $settlement['final_price'],
                'buyerPartyId' => $settlement['buyer_party_id'], 'sellerPartyId' => $settlement['seller_party_id'],
            ]);
        }

        $final = $this->settlementModel->find($settlementId);

        // D-109: checkCompletion() is the single funnel every settlement
        // action passes through (both NOC confirmations, both ratings,
        // and forceResolveStalled) — one broadcast point here covers all
        // of them, same reasoning as OfferService's acceptOffer (D-108).
        // Unlike the Buy-Now listing page, a settlement is a private
        // two-party document with no general "any visitor" audience, so
        // this goes only to the buyer's and seller's own party channels
        // (buyer:<partyId> — reused as-is, not a new room type) and
        // never to a sale_event room.
        $broadcaster = new RealtimeBroadcastService();
        $payload = [
            'settlementId' => $settlementId,
            'status' => $final['status'],
            'sellerNocConfirmed' => (bool) $final['seller_noc_confirmed_at'],
            'buyerNocConfirmed' => (bool) $final['buyer_noc_confirmed_at'],
            'buyerRatedSeller' => (bool) $final['buyer_rated_seller_at'],
            'sellerRatedBuyer' => (bool) $final['seller_rated_buyer_at'],
        ];
        $broadcaster->broadcastToBuyer($final['buyer_party_id'], 'settlement_updated', $payload);
        $broadcaster->broadcastToBuyer($final['seller_party_id'], 'settlement_updated', $payload);

        return $final;
    }

    // BR-49/PR-27
    private function maybeRecordHighValueDisposal(string $settlementId, array $settlement): void
    {
        $finalValue = (float) $settlement['final_price'];
        $threshold = SovereignRuleService::getNumeric('BR-49.high_value_threshold', self::HIGH_VALUE_DISPOSAL_THRESHOLD_DEFAULT);
        if ($finalValue <= $threshold) {
            return;
        }

        $saleEvent = $this->saleEventModel->find($settlement['sale_event_id']);
        $reserveValue = $saleEvent['reserve_value'] !== null ? (float) $saleEvent['reserve_value']
            : ($saleEvent['expected_value'] !== null ? (float) $saleEvent['expected_value'] : null);
        $variance = $reserveValue !== null ? round($finalValue - $reserveValue, 2) : 0.0;

        $db = \Config\Database::connect();
        $db->table('high_value_disposal_record')->insert([
            'id' => Uuid::v4(),
            'settlement_id' => $settlementId,
            'sale_event_id' => $saleEvent['id'],
            'tenant_id' => $saleEvent['tenant_id'],
            'sale_format' => $saleEvent['sale_format'],
            'reserve_value' => $reserveValue,
            'final_sale_value' => $finalValue,
            'variance' => $variance,
        ]);

        (new AuditLogService())->log('settlement.high_value_disposal_flagged', null, [
            'settlementId' => $settlementId, 'saleEventId' => $saleEvent['id'],
            'finalSaleValue' => $finalValue, 'reserveValue' => $reserveValue, 'variance' => $variance,
        ]);
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
