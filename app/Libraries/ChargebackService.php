<?php

namespace App\Libraries;

use App\Models\ChargebackCaseModel;
use App\Models\EmdHoldModel;
use App\Models\BidModel;

// BR-52/PR-30: Chargeback Handling & Representment.
//
// Built against real data throughout -- the evidence package genuinely
// pulls the actual consent record, bid/offer history, and forfeiture
// chain for the pledge being disputed. What's NOT real, per the same
// honesty standard as every other payment-gateway-dependent piece of
// this app (AmlMonitoringService::detectSharedFundingSource,
// EmdConsentController's dev-fund routes): PR-30 step 190's
// authorization-hold-vs-capture timing and step 192's actual submission
// to the card network both require the real Payment Gateway integration
// (already a tracked, accepted external dependency -- see
// docs/DECISIONS.md). Filing itself is exposed through a dev-only route
// (ChargebackController::devFile), the same pattern as
// BidController::devFundEmd/devPayTopup, standing in for the gateway
// webhook that would deliver a real chargeback notice in production.
class ChargebackService
{
    private ChargebackCaseModel $caseModel;
    private EmdHoldModel $holdModel;
    private BidModel $bidModel;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->caseModel = new ChargebackCaseModel();
        $this->holdModel = new EmdHoldModel();
        $this->bidModel = new BidModel();
        $this->db = \Config\Database::connect();
    }

    // PR-30 steps 189-193: files the case and, in the same call,
    // auto-assembles the evidence package (step 191) -- there's no
    // separate human step to assemble it, only to eventually record
    // what the gateway decided.
    public function fileChargeback(string $emdHoldId, string $filedReason): array
    {
        $hold = $this->holdModel->find($emdHoldId);
        if (!$hold) {
            throw new \RuntimeException('EMD hold not found.');
        }

        $againstApprovedForfeiture = $hold['status'] === 'forfeited';
        $evidence = $this->assembleEvidence($hold, $againstApprovedForfeiture);

        $case = $this->caseModel->createCase([
            'emd_hold_id' => $emdHoldId,
            'sale_event_id' => $hold['sale_event_id'],
            'party_id' => $hold['party_id'],
            'amount' => $hold['amount'],
            'filed_reason' => $filedReason,
            'against_approved_forfeiture' => $againstApprovedForfeiture,
            'evidence_package' => json_encode($evidence, JSON_UNESCAPED_SLASHES),
            // Evidence assembly is instantaneous and real; "represented"
            // reflects that the package is ready for submission -- actual
            // transmission to the gateway is the accepted external gap
            // noted above.
            'status' => 'represented',
            'evidence_assembled_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditLogService())->log('chargeback.filed', $hold['party_id'], [
            'caseId' => $case['id'], 'emdHoldId' => $emdHoldId, 'saleEventId' => $hold['sale_event_id'],
            'amount' => $hold['amount'], 'againstApprovedForfeiture' => $againstApprovedForfeiture,
        ]);

        // BR-52/PR-30 step 193: "logged as a distinct account-integrity
        // event on the buyer's record, independent of the representment
        // outcome" -- this audit entry IS that distinct log, separate
        // from the generic chargeback.filed entry above regardless of
        // whether a SaaS Admin later applies a rating consequence.
        if ($againstApprovedForfeiture) {
            (new AuditLogService())->log('chargeback.against_approved_forfeiture', $hold['party_id'], [
                'caseId' => $case['id'], 'emdHoldId' => $emdHoldId, 'saleEventId' => $hold['sale_event_id'],
                'forfeitedAt' => $hold['forfeited_at'],
            ]);
        }

        return $case;
    }

    private function assembleEvidence(array $hold, bool $againstApprovedForfeiture): array
    {
        $consent = $this->db->table('consent_event')
            ->where('party_id', $hold['party_id'])
            ->where('consent_type', 'emd_pledge')
            ->where('related_reference_id', $hold['sale_event_id'])
            ->orderBy('created_at', 'ASC')
            ->get()->getRowArray();

        $bids = $this->bidModel->where('sale_event_id', $hold['sale_event_id'])
            ->where('bidder_party_id', $hold['party_id'])
            ->orderBy('placed_at', 'ASC')->findAll();

        $offers = $this->db->table('offer')
            ->where('sale_event_id', $hold['sale_event_id'])
            ->where('buyer_party_id', $hold['party_id'])
            ->orderBy('created_at', 'ASC')->get()->getResultArray();

        $evidence = [
            'consentRecord' => $consent ? [
                'consentTextShown' => $consent['consent_text_shown'],
                'termsVersion' => $consent['terms_version'],
                'recordedAt' => $consent['created_at'],
                'ipAddress' => $consent['ip_address'],
            ] : null,
            'bidTransactionHistory' => array_map(
                static fn ($b) => ['amount' => $b['amount'], 'standing' => $b['standing'], 'at' => $b['placed_at']],
                $bids
            ),
            'offerTransactionHistory' => array_map(
                static fn ($o) => ['amount' => $o['amount'], 'status' => $o['status'], 'at' => $o['created_at']],
                $offers
            ),
            'emdHold' => [
                'amount' => $hold['amount'], 'channel' => $hold['channel'], 'status' => $hold['status'],
            ],
        ];

        if ($againstApprovedForfeiture) {
            $evidence['forfeitureApprovalChain'] = [
                'forfeitedAt' => $hold['forfeited_at'],
                'forfeitedToTenantAmount' => $hold['forfeited_to_tenant_amount'],
                'forfeitedToSaasAmount' => $hold['forfeited_to_saas_amount'],
                'forfeitedToSellerAmount' => $hold['forfeited_to_seller_amount'],
            ];
        }

        return $evidence;
    }

    // SaaS Admin manually records what the gateway ultimately decided --
    // the honest stand-in for a real representment-response webhook.
    public function recordRepresentmentOutcome(string $caseId, string $adminId, string $outcome, string $notes): array
    {
        $case = $this->caseModel->find($caseId);
        if (!$case) {
            throw new \RuntimeException('Chargeback case not found.');
        }
        if ($case['status'] !== 'represented') {
            throw new \RuntimeException('This case is not awaiting a representment outcome.');
        }
        if (!in_array($outcome, ['won', 'lost'], true)) {
            throw new \RuntimeException('Invalid representment outcome.');
        }

        $status = $outcome === 'won' ? 'resolved_won' : 'resolved_lost';
        $case = $this->caseModel->markRepresentmentOutcome($caseId, $adminId, $status, $notes);

        (new AuditLogService())->log('chargeback.representment_resolved', $adminId, [
            'caseId' => $caseId, 'partyId' => $case['party_id'], 'outcome' => $outcome, 'notes' => $notes,
        ]);

        return $case;
    }

    // BR-52/PR-30 step 193: SaaS Admin reviews a chargeback filed against
    // an already-approved, legitimate forfeiture and decides whether it
    // warrants the RatingService::NAMED_EVENTS
    // 'chargeback_against_approved_forfeiture' penalty (-2.0 star_rating,
    // previously defined but never wired to a real caller -- see
    // docs/SCREEN_COMPLETENESS_AUDIT.md finding #1). A SaaS Admin
    // finding here is the ultimate authority BR-36's approval gate
    // exists to require, so -- same pattern as
    // RatingService::delistSellerForFraud -- it self-approves at both
    // tiers rather than sitting pending.
    public function reviewIntegrityFlag(string $caseId, string $superAdminId, bool $applyRatingConsequence, string $notes): array
    {
        $case = $this->caseModel->find($caseId);
        if (!$case) {
            throw new \RuntimeException('Chargeback case not found.');
        }
        if (!$case['against_approved_forfeiture']) {
            throw new \RuntimeException('This case was not filed against an approved forfeiture.');
        }
        if ($case['integrity_reviewed_at'] !== null) {
            throw new \RuntimeException('This case has already been reviewed.');
        }

        if ($applyRatingConsequence) {
            $rating = new RatingService();
            $downgrade = $rating->applyNamedEvent(
                $case['party_id'], 'star_rating', 'chargeback_against_approved_forfeiture', $notes, $case['sale_event_id']
            );
            $rating->approveDowngrade($downgrade['id'], $superAdminId, 'tenant_admin');
            if ($downgrade['requiresDualApproval']) {
                $rating->approveDowngrade($downgrade['id'], $superAdminId, 'super_admin');
            }
        }

        $case = $this->caseModel->markIntegrityReviewed($caseId, $superAdminId, $notes, $applyRatingConsequence);

        (new AuditLogService())->log('chargeback.integrity_reviewed', $superAdminId, [
            'caseId' => $caseId, 'partyId' => $case['party_id'],
            'ratingConsequenceApplied' => $applyRatingConsequence, 'notes' => $notes,
        ]);

        return $case;
    }
}
