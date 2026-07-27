<?php

namespace App\Libraries;

use App\Models\AmlFlagModel;

// BR-54/PR-31: Anti-Money Laundering Transaction Monitoring.
//
// Built literally to the governing text — three named patterns, one
// SaaS-Admin-only review queue, one STR-filing decision, logged to the
// existing immutable audit trail (BR-05). Deliberately NOT the larger
// risk-scoring/case-management/device-intelligence platform discussed
// alongside this BR — that was flagged as a genuine deviation from the
// source document and explicitly deferred pending the project owner's
// own decision (see docs/DECISIONS.md).
//
// PR-31 is explicit that flags are "visible only to SaaS Admin — not to
// the User or any Tenant Admin" — enforced by every entry point into
// this service being gated behind the superAdmin filter (AmlController),
// not re-checked in here.
class AmlMonitoringService
{
    // Not fixed by BR-54's text — reasonable defaults, flagged the same
    // way the 7-day settlement-stall threshold (D-25) and OTP-attempt
    // limit (AuthService) were: a working starting point, not a settled
    // business rule requiring no further confirmation.
    private const DEPOSIT_REFUND_CYCLE_THRESHOLD = 3;
    private const DEPOSIT_REFUND_WINDOW_DAYS = 14;
    private const KYC_INCONSISTENT_RATIO = 0.5;

    private AmlFlagModel $flagModel;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->flagModel = new AmlFlagModel();
        $this->db = \Config\Database::connect();
    }

    // BR-54: "rapid deposit-then-refund cycles with no genuine bidding
    // activity." The one pattern with real, populated data to check
    // today. A "cycle" = an emd_hold funded and later released without
    // the funding party ever placing a bid or offer on that sale_event —
    // money moved in and back out with no actual participation behind
    // it. Genuine losers (who did bid/offer but didn't win) are
    // correctly excluded — this only catches funding with zero
    // participation at all.
    public function detectDepositRefundCycles(): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-' . self::DEPOSIT_REFUND_WINDOW_DAYS . ' days'));

        $released = $this->db->table('emd_hold')
            ->select('id, sale_event_id, party_id, amount')
            ->where('status', 'released')
            ->where('held_at >=', $since)
            ->get()->getResultArray();

        $noParticipation = [];
        foreach ($released as $hold) {
            $bidExists = $this->db->table('bid')
                ->where('sale_event_id', $hold['sale_event_id'])
                ->where('bidder_party_id', $hold['party_id'])
                ->countAllResults() > 0;
            $offerExists = $this->db->table('offer')
                ->where('sale_event_id', $hold['sale_event_id'])
                ->where('buyer_party_id', $hold['party_id'])
                ->countAllResults() > 0;

            if (!$bidExists && !$offerExists) {
                $noParticipation[$hold['party_id']][] = $hold;
            }
        }

        $flagged = [];
        foreach ($noParticipation as $partyId => $holds) {
            if (count($holds) < self::DEPOSIT_REFUND_CYCLE_THRESHOLD) {
                continue;
            }
            if ($this->flagModel->hasOpenFlag($partyId, 'deposit_refund_cycle')) {
                continue;
            }
            $this->flagModel->createFlag('deposit_refund_cycle', $partyId, [
                'occurrences' => count($holds),
                'windowDays' => self::DEPOSIT_REFUND_WINDOW_DAYS,
                'saleEventIds' => array_column($holds, 'sale_event_id'),
                'totalAmount' => array_sum(array_column($holds, 'amount')),
            ]);
            (new AuditLogService())->log('aml.flag_raised', null, [
                'partyId' => $partyId, 'flagType' => 'deposit_refund_cycle', 'occurrences' => count($holds),
            ]);
            $flagged[] = $partyId;
        }
        return $flagged;
    }

    // BR-54: "deposits inconsistent with a User's declared KYC profile."
    //
    // ⚠️ HONEST LIMITATION: no real KYC data-entry flow exists yet
    // (BR-17/18, deferred to Tier 4) — party.org_annual_turnover is a
    // real column but nothing currently populates it. This check is
    // genuinely wired against real data and will fire correctly the
    // moment that data exists; until then it will realistically flag
    // nothing, since virtually every party has a null value here. Not
    // faked — the logic is real, the upstream input just isn't
    // collected yet, the same category of gap as BR-45's GPS capture.
    public function detectKycInconsistentDeposits(): array
    {
        $held = $this->db->table('emd_hold eh')
            ->select('eh.party_id, eh.amount, p.org_annual_turnover')
            ->join('party p', 'p.id = eh.party_id')
            ->where('eh.status', 'held')
            ->where('p.org_annual_turnover IS NOT NULL')
            ->get()->getResultArray();

        $flagged = [];
        foreach ($held as $row) {
            $turnover = (float) $row['org_annual_turnover'];
            $amount = (float) $row['amount'];
            if ($turnover <= 0 || $amount <= $turnover * self::KYC_INCONSISTENT_RATIO) {
                continue;
            }
            if ($this->flagModel->hasOpenFlag($row['party_id'], 'kyc_inconsistent_deposit')) {
                continue;
            }
            $this->flagModel->createFlag('kyc_inconsistent_deposit', $row['party_id'], [
                'depositAmount' => $amount,
                'declaredAnnualTurnover' => $turnover,
            ]);
            (new AuditLogService())->log('aml.flag_raised', null, [
                'partyId' => $row['party_id'], 'flagType' => 'kyc_inconsistent_deposit',
            ]);
            $flagged[] = $row['party_id'];
        }
        return $flagged;
    }

    // BR-54: "multiple accounts funding or being funded from the same
    // external bank account."
    //
    // ⚠️ HONEST LIMITATION: NOT wired to fire in practice today.
    // emd_hold.gateway_reference is the schema field this keys off, but
    // confirmed by checking every createHold() call site in this
    // codebase — nothing ever populates it, since the payment gateway
    // itself is stubbed (deferred post-deployment, D-23). Detecting this
    // pattern against a column nothing writes real values into would be
    // security theater, not a real control — same honesty standard as
    // BR-59's stock-photo detection being explicitly out of scope. The
    // query is correct and will catch real collisions the moment a real
    // gateway starts populating gateway_reference; until then it
    // structurally returns empty every run.
    public function detectSharedFundingSource(): array
    {
        $groups = $this->db->table('emd_hold')
            ->select('gateway_reference')
            ->where('gateway_reference IS NOT NULL')
            ->groupBy('gateway_reference')
            ->having('COUNT(DISTINCT party_id) >', 1)
            ->get()->getResultArray();

        $flagged = [];
        foreach ($groups as $group) {
            $parties = $this->db->table('emd_hold')
                ->distinct()->select('party_id')
                ->where('gateway_reference', $group['gateway_reference'])
                ->get()->getResultArray();

            foreach ($parties as $p) {
                if ($this->flagModel->hasOpenFlag($p['party_id'], 'shared_funding_source')) {
                    continue;
                }
                $this->flagModel->createFlag('shared_funding_source', $p['party_id'], [
                    'sharedReference' => $group['gateway_reference'],
                ]);
                (new AuditLogService())->log('aml.flag_raised', null, [
                    'partyId' => $p['party_id'], 'flagType' => 'shared_funding_source',
                ]);
                $flagged[] = $p['party_id'];
            }
        }
        return $flagged;
    }

    public function runAll(): array
    {
        return [
            'depositRefundCyclesFlagged' => $this->detectDepositRefundCycles(),
            'kycInconsistentDepositsFlagged' => $this->detectKycInconsistentDeposits(),
            'sharedFundingSourceFlagged' => $this->detectSharedFundingSource(),
        ];
    }

    // PR-31: SaaS Admin reviews a flag as either explainable (no action)
    // or warranting a Suspicious Transaction Report under PMLA. Every
    // flag and its resolution is logged to the audit trail regardless
    // of outcome, per PR-31's explicit final step.
    public function reviewFlag(string $flagId, string $reviewerPartyId, string $decision, ?string $strReference, string $notes): array
    {
        $flag = $this->flagModel->find($flagId);
        if (!$flag) {
            throw new \RuntimeException('AML flag not found.');
        }
        if ($flag['status'] !== 'open') {
            throw new \RuntimeException('This flag has already been reviewed.');
        }
        if (!in_array($decision, ['no_action', 'str_filed'], true)) {
            throw new \RuntimeException('Invalid review decision.');
        }
        if ($decision === 'str_filed' && !$strReference) {
            throw new \RuntimeException('A Suspicious Transaction Report reference is required when filing an STR.');
        }

        $status = $decision === 'str_filed' ? 'reviewed_str_filed' : 'reviewed_no_action';
        $this->flagModel->markReviewed($flagId, $reviewerPartyId, $status, $strReference, $notes);

        (new AuditLogService())->log('aml.flag_reviewed', $reviewerPartyId, [
            'flagId' => $flagId, 'partyId' => $flag['party_id'], 'flagType' => $flag['flag_type'],
            'decision' => $decision, 'strReference' => $strReference, 'notes' => $notes,
        ]);

        return $this->flagModel->find($flagId);
    }
}
