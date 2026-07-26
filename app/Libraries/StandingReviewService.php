<?php

namespace App\Libraries;

use App\Models\DisputeModel;
use App\Models\PartyModel;
use App\Models\SellerApplicationModel;

class StandingReviewService
{
    private const COMPLAINT_THRESHOLD = 10;

    private DisputeModel $disputeModel;
    private PartyModel $partyModel;
    private SellerApplicationModel $sellerApplicationModel;

    public function __construct()
    {
        $this->disputeModel = new DisputeModel();
        $this->partyModel = new PartyModel();
        $this->sellerApplicationModel = new SellerApplicationModel();
    }

    public function recordComplaint(string $sellerId, string $reason): void
    {
        $party = $this->partyModel->find($sellerId);
        $newCount = (int) $party['standing_review_complaint_count'] + 1;
        $this->partyModel->update($sellerId, ['standing_review_complaint_count' => $newCount]);

        (new AuditLogService())->log('standing_review.complaint_recorded', $sellerId, [
            'reason' => $reason, 'newCount' => $newCount,
        ]);

        if ($newCount > self::COMPLAINT_THRESHOLD && !$this->hasOpenCase($sellerId)) {
            $this->openCase($sellerId, "Complaint count exceeded " . self::COMPLAINT_THRESHOLD . " (count-triggered)");
        }
    }

    public function recordCbsViolation(string $sellerId, string $flaggedByPartyId, string $listingId): array
    {
        $party = $this->partyModel->find($sellerId);
        $offenseNumber = (int) $party['standing_review_cbs_offense_count'] + 1;
        $this->partyModel->update($sellerId, ['standing_review_cbs_offense_count' => $offenseNumber]);

        (new AuditLogService())->log('standing_review.cbs_violation_flagged', $flaggedByPartyId, [
            'sellerId' => $sellerId, 'listingId' => $listingId, 'offenseNumber' => $offenseNumber,
        ]);

        $tier = match (true) {
            $offenseNumber <= 2 => 'warning',
            $offenseNumber <= 4 => 'tenant_admin_discretion',
            default => 'saas_admin_exclusive',
        };

        $this->recordComplaint($sellerId, "CBS violation (offense #{$offenseNumber}, tier: {$tier})");

        return ['offenseNumber' => $offenseNumber, 'tier' => $tier];
    }

    public function hasOpenCase(string $sellerId): bool
    {
        return $this->disputeModel
            ->where('respondent_party_id', $sellerId)
            ->where('category', 'standing_review')
            ->whereIn('status', ['filed', 'evidence_window', 'ruled', 'appealed'])
            ->countAllResults() > 0;
    }

    public function openCase(string $sellerId, string $triggerReason): array
    {
        if ($this->hasOpenCase($sellerId)) {
            throw new \RuntimeException('BR-61: this seller already has an open Standing Review case.');
        }

        $created = $this->disputeModel->createDispute([
            'sale_event_id' => null,
            'filed_by_party_id' => null,
            'respondent_party_id' => $sellerId,
            'category' => 'standing_review',
            'description' => $triggerReason,
            'status' => 'filed',
            'ruling_authority_type' => 'tenant_admin',
        ]);

        (new AuditLogService())->log('standing_review.case_opened', null, [
            'disputeId' => $created['id'], 'sellerId' => $sellerId, 'triggerReason' => $triggerReason,
        ]);

        return $created;
    }

    public function ruleOnCase(string $disputeId, string $rulerPartyId, string $tenantId, string $outcome, string $rationale, ?float $ratingConsequence = null): array
    {
        $dispute = $this->disputeModel->find($disputeId);
        if (!$dispute || $dispute['category'] !== 'standing_review') {
            throw new \RuntimeException('Not a Standing Review case.');
        }
        if (!in_array($dispute['status'], ['filed', 'evidence_window'], true)) {
            throw new \RuntimeException('This case has already been ruled on.');
        }
        if (!in_array($outcome, ['no_action', 'escalation_consequence', 'suspension'], true)) {
            throw new \RuntimeException('Invalid Standing Review outcome.');
        }

        $authz = new AuthorizationService();
        if (!$authz->isTenantAdminFor($rulerPartyId, $tenantId) && !$authz->isSuperAdmin($rulerPartyId)) {
            throw new \RuntimeException('BR-61: this case requires ruling by a Tenant Admin for one of the seller\'s tenants, or Super Admin.');
        }

        $sellerId = $dispute['respondent_party_id'];

        if ($outcome === 'escalation_consequence' && $ratingConsequence !== null) {
            (new RatingService())->initiateDowngrade($sellerId, 'seller_star_rating', $ratingConsequence, "Standing Review escalation: {$rationale}");
        }
        if ($outcome === 'suspension') {
            (new SellerApplicationService())->suspendSeller($sellerId, $tenantId, $rulerPartyId, "Standing Review: {$rationale}");
        }

        $this->disputeModel->update($disputeId, [
            'status' => 'ruled', 'ruled_by_party_id' => $rulerPartyId,
            'ruling_outcome' => match ($outcome) {
                'no_action' => 'dismissed',
                'escalation_consequence' => 'rating_consequence',
                'suspension' => 'suspension',
            },
            'ruling_rationale' => $rationale, 'ruled_at' => date('Y-m-d H:i:s'),
        ]);

        $this->partyModel->update($sellerId, ['standing_review_complaint_count' => 0]);

        (new AuditLogService())->log('standing_review.case_ruled', $rulerPartyId, [
            'disputeId' => $disputeId, 'sellerId' => $sellerId, 'outcome' => $outcome, 'rationale' => $rationale,
        ]);

        return $this->disputeModel->find($disputeId);
    }

    public function checkAnnualAnniversary(string $sellerId): bool
    {
        $party = $this->partyModel->find($sellerId);
        $firstApproval = $this->sellerApplicationModel
            ->where('party_id', $sellerId)->where('status', 'approved')
            ->orderBy('decided_at', 'ASC')->first();

        if (!$firstApproval) {
            return false;
        }

        $nextAnnual = $party['standing_review_next_annual_at'];
        if ($nextAnnual === null) {
            $anchor = (new \DateTimeImmutable($firstApproval['decided_at']))->modify('+12 months');
            $this->partyModel->update($sellerId, ['standing_review_next_annual_at' => $anchor->format('Y-m-d H:i:s')]);
            return false;
        }

        if (strtotime($nextAnnual) <= time()) {
            if (!$this->hasOpenCase($sellerId)) {
                $this->openCase($sellerId, 'Annual Standing Review anniversary');
            }
            $next = (new \DateTimeImmutable($nextAnnual))->modify('+12 months');
            $this->partyModel->update($sellerId, ['standing_review_next_annual_at' => $next->format('Y-m-d H:i:s')]);
            return true;
        }

        return false;
    }
}
