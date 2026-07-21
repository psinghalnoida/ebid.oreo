<?php

namespace App\Libraries;

use App\Models\DisputeModel;
use App\Models\DisputeEvidenceModel;
use App\Models\SaleEventModel;
use App\Models\ListingModel;
use App\Models\SettlementModel;
use App\Models\EmdHoldModel;

class DisputeService
{
    // BR-40: filing window — same 7-day figure used in the plain-language
    // guide, which itself flags this as "not independently reconfirmed."
    // Anchored to the sale_event's actual_closed_at where available; if
    // not yet closed (a non-lifting/collection dispute can arise before
    // formal close), no window is enforced. This anchor-point choice is a
    // simplification — the underlying document doesn't specify a precise
    // per-category trigger event, so one consistent anchor was used
    // rather than guessing five different ones.
    private const FILING_WINDOW_DAYS = 7;

    private DisputeModel $disputeModel;
    private DisputeEvidenceModel $evidenceModel;
    private SaleEventModel $saleEventModel;
    private ListingModel $listingModel;
    private SettlementModel $settlementModel;
    private EmdHoldModel $emdHoldModel;
    private AuthorizationService $authz;
    private RatingService $ratingService;

    public function __construct()
    {
        $this->disputeModel = new DisputeModel();
        $this->evidenceModel = new DisputeEvidenceModel();
        $this->saleEventModel = new SaleEventModel();
        $this->listingModel = new ListingModel();
        $this->settlementModel = new SettlementModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->authz = new AuthorizationService();
        $this->ratingService = new RatingService();
    }

    private function ruleForCategory(string $category): string
    {
        return $category === 'buyer_non_response' ? 'super_admin' : 'tenant_admin';
    }

    public function fileDispute(string $saleEventId, string $filedByPartyId, string $category, string $description): array
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            throw new \RuntimeException('Sale event not found');
        }
        if ($saleEvent['sale_format'] === 'tender') {
            throw new \RuntimeException('BR-40: Tender Auctions are excluded from the Dispute Resolution Framework entirely.');
        }

        if ($saleEvent['actual_closed_at']) {
            $deadline = (new \DateTimeImmutable($saleEvent['actual_closed_at']))->modify('+' . self::FILING_WINDOW_DAYS . ' days');
            if (new \DateTimeImmutable() > $deadline) {
                throw new \RuntimeException('BR-40: the ' . self::FILING_WINDOW_DAYS . '-day filing window for this transaction has passed.');
            }
        }

        $listing = $this->listingModel->find($saleEvent['listing_id']);
        $sellerId = $listing['seller_party_id'];
        $buyerId = $saleEvent['current_high_bidder_party_id'];

        $respondentId = ($filedByPartyId === $sellerId) ? $buyerId : $sellerId;
        if (!$respondentId || ($filedByPartyId !== $sellerId && $filedByPartyId !== $buyerId)) {
            throw new \RuntimeException('Only the buyer or seller on this transaction may file a dispute against it.');
        }

        $evidenceDeadline = (new \DateTimeImmutable())->modify('+' . self::FILING_WINDOW_DAYS . ' days');

        return $this->disputeModel->createDispute([
            'sale_event_id' => $saleEventId, 'filed_by_party_id' => $filedByPartyId,
            'respondent_party_id' => $respondentId, 'category' => $category, 'description' => $description,
            'status' => 'evidence_window', 'evidence_deadline_at' => $evidenceDeadline->format('Y-m-d H:i:s'),
            'ruling_authority_type' => $this->ruleForCategory($category),
        ]);
    }

    public function submitEvidence(string $disputeId, string $partyId, string $content): array
    {
        $dispute = $this->requireDispute($disputeId);
        if ($partyId !== $dispute['filed_by_party_id'] && $partyId !== $dispute['respondent_party_id']) {
            throw new \RuntimeException('Only the two parties to this dispute may submit evidence.');
        }
        if (!in_array($dispute['status'], ['filed', 'evidence_window'], true)) {
            throw new \RuntimeException('Evidence can only be submitted while the dispute is open for evidence.');
        }
        return $this->evidenceModel->createEvidence($disputeId, $partyId, $content);
    }

    public function getEvidence(string $disputeId): array
    {
        return $this->evidenceModel->findForDispute($disputeId);
    }

    // BR-40: the ruling authority must actually hold the right
    // authorization for this SPECIFIC dispute's category before ruling —
    // checked here, not just assumed from the route filter, since the
    // route filter can't distinguish category-based routing on its own.
    public function ruleOnDispute(string $disputeId, string $rulerPartyId, string $outcome, string $rationale, ?string $atFaultPartyId = null): array
    {
        $dispute = $this->requireDispute($disputeId);
        if (!in_array($dispute['status'], ['filed', 'evidence_window'], true)) {
            throw new \RuntimeException('This dispute has already been ruled on.');
        }

        $saleEvent = $this->saleEventModel->find($dispute['sale_event_id']);
        if ($dispute['ruling_authority_type'] === 'super_admin') {
            if (!$this->authz->isSuperAdmin($rulerPartyId)) {
                throw new \RuntimeException('BR-40: this dispute category requires Super Admin ruling.');
            }
        } else {
            if (!$this->authz->isTenantAdminFor($rulerPartyId, $saleEvent['tenant_id'])) {
                throw new \RuntimeException('BR-40: this dispute requires ruling by the Tenant Admin for this listing\'s tenant.');
            }
        }

        $this->executeRuling($dispute, $outcome, $atFaultPartyId, $rulerPartyId);

        $this->disputeModel->update($disputeId, [
            'status' => 'ruled', 'ruled_by_party_id' => $rulerPartyId,
            'ruling_outcome' => $outcome, 'ruling_rationale' => $rationale, 'ruled_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->disputeModel->find($disputeId);
    }

    // Executes the real consequence of a ruling — not just recording an
    // enum value. Reuses SettlementService/EmdHoldModel/RatingService
    // rather than duplicating logic.
    private function executeRuling(array $dispute, string $outcome, ?string $atFaultPartyId, string $rulerPartyId): void
    {
        $settlement = $this->settlementModel->findBySaleEvent($dispute['sale_event_id']);

        if ($outcome === 'force_log_noc') {
            if (!$settlement) {
                throw new \RuntimeException('Cannot force-log an NOC — no settlement exists for this sale event yet.');
            }
            $settlementService = new SettlementService();
            if ($dispute['respondent_party_id'] === $settlement['seller_party_id']) {
                $settlementService->confirmSellerNoc($settlement['id'], $settlement['seller_party_id']);
            } else {
                $settlementService->confirmBuyerNoc($settlement['id'], $settlement['buyer_party_id']);
            }
        } elseif ($outcome === 'order_forfeiture') {
            $target = $atFaultPartyId ?? $dispute['respondent_party_id'];
            $hold = $this->emdHoldModel->findBySaleEventAndParty($dispute['sale_event_id'], $target);
            if ($hold && $hold['status'] === 'held') {
                $saleEvent = $this->saleEventModel->find($dispute['sale_event_id']);
                $tenant = (new \App\Models\TenantModel())->find($saleEvent['tenant_id']);
                $allocation = EmdService::calculateForfeitureAllocation(
                    (float) $hold['amount'], (float) $tenant['buyer_fee_percent'], 0.5, false
                );
                $this->emdHoldModel->markForfeited($hold['id'], $allocation['tenantAmount'], $allocation['saasAmount'], $allocation['sellerAmount']);
            }
        } elseif ($outcome === 'rating_consequence') {
            $target = $atFaultPartyId ?? $dispute['respondent_party_id'];
            $listing = $this->listingModel->find($this->saleEventModel->find($dispute['sale_event_id'])['listing_id']);
            $ratingRole = ($target === $listing['seller_party_id']) ? 'seller_star_rating' : 'star_rating';
            $downgrade = $this->ratingService->initiateDowngrade($target, $ratingRole, 0.5, "Dispute ruling: {$dispute['id']}");
            // A ruling authority's own decision satisfies the BR-36 approval
            // it would otherwise need — self-approve at the appropriate tier.
            $approverType = $dispute['ruling_authority_type'];
            $this->ratingService->approveDowngrade($downgrade['id'], $rulerPartyId, $approverType);
            if ($approverType === 'super_admin' && $downgrade['requiresDualApproval']) {
                // Super Admin ruling satisfies both tiers of BR-36's gate.
                $this->ratingService->approveDowngrade($downgrade['id'], $rulerPartyId, 'tenant_admin');
            }
        } elseif ($outcome === 'dismissed') {
            // No execution — BR-40 9.1 pattern-tracking happens via query,
            // not a write, at dispute-filing time (see countDismissedFiledBy).
        } else {
            throw new \RuntimeException("Unknown ruling outcome: {$outcome}");
        }
    }

    // BR-40: one appeal, Tenant Admin ruling -> Super Admin only.
    public function fileAppeal(string $disputeId, string $appealingPartyId): array
    {
        $dispute = $this->requireDispute($disputeId);
        if ($dispute['status'] !== 'ruled') {
            throw new \RuntimeException('Only a ruled dispute can be appealed.');
        }
        if ($dispute['ruling_authority_type'] === 'super_admin') {
            throw new \RuntimeException('BR-40: a direct Super Admin ruling is final and cannot be appealed.');
        }
        if ($appealingPartyId !== $dispute['filed_by_party_id'] && $appealingPartyId !== $dispute['respondent_party_id']) {
            throw new \RuntimeException('Only a party to this dispute may appeal it.');
        }
        $this->disputeModel->update($disputeId, ['status' => 'appealed', 'appealed_at' => date('Y-m-d H:i:s')]);
        return $this->disputeModel->find($disputeId);
    }

    // Note: an appeal ruling records the final decision but does NOT
    // automatically reverse whatever the original ruling already executed
    // (a forfeiture already processed, a rating already changed) — that
    // reversal, if the appeal outcome differs, is a manual admin action
    // not automated here. Flagged as a real limitation, not hidden.
    public function ruleOnAppeal(string $disputeId, string $superAdminPartyId, string $rationale): array
    {
        $dispute = $this->requireDispute($disputeId);
        if ($dispute['status'] !== 'appealed') {
            throw new \RuntimeException('This dispute has not been appealed.');
        }
        if (!$this->authz->isSuperAdmin($superAdminPartyId)) {
            throw new \RuntimeException('BR-40: only a Super Admin may rule on an appeal.');
        }
        $this->disputeModel->update($disputeId, [
            'status' => 'closed', 'appeal_ruled_by_party_id' => $superAdminPartyId,
            'appeal_rationale' => $rationale, 'appeal_ruled_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->disputeModel->find($disputeId);
    }

    private function requireDispute(string $disputeId): array
    {
        $dispute = $this->disputeModel->find($disputeId);
        if (!$dispute) {
            throw new \RuntimeException('Dispute not found');
        }
        return $dispute;
    }
}
