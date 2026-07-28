<?php

namespace App\Libraries;

use App\Models\AmlFlagModel;
use App\Models\EmdHoldModel;

// BR-54 / PR-31: Anti-Money Laundering Transaction Monitoring — "distinct
// from the fishing/circumvention detection described elsewhere." Flags are
// visible only to SaaS Admin (AmlAdminController, superAdmin filter) —
// never surfaced to the User being flagged or to any Tenant Admin.
//
// BR-54 names three monitored patterns. Two are built here, genuinely
// detectable against real data already in this schema. The third —
// "deposits inconsistent with a User's declared KYC profile" — is NOT
// built: BR-17 (KYC verification) is itself still an unwired schema-only
// stub (PartyModel::setKycStatus is never called anywhere; every party
// sits at the DB default kyc_status='pending' forever, and the
// declared-profile fields like org_annual_turnover are never populated by
// any UI). Comparing deposit amounts against a "declared KYC profile"
// that doesn't actually exist yet for any real user would be theatre, not
// monitoring — flagged honestly here and in docs/DECISIONS.md rather than
// faked, the same treatment BR-46/BR-52 already get for their own
// external blockers. This can be added for real once BR-17 is built.
class AmlMonitoringService
{
    // BR-54's text says "rapid" but doesn't define a threshold. No fixed
    // number is given anywhere in the source spec, so this is a reasonable
    // starting default, not a value taken from the BR/PR document — flagged
    // here and in DECISIONS.md so it reads as a judgment call, not as if
    // BR-54 specified it. Adjustable without a schema change if SaaS Admin
    // decides a different window is more appropriate once real volume exists.
    private const RAPID_CYCLE_HOURS = 24;

    private AmlFlagModel $flagModel;
    private EmdHoldModel $emdHoldModel;

    public function __construct()
    {
        $this->flagModel = new AmlFlagModel();
        $this->emdHoldModel = new EmdHoldModel();
    }

    // Pattern 1: "rapid deposit-then-refund cycles with no genuine bidding
    // activity." Only considers holds released while their sale_event was
    // NOT cancelled — an emergency-stopped/withdrawn listing releases every
    // participant's EMD indiscriminately (ListingLifecycleService), which
    // is the platform pulling the auction out from under genuine bidders,
    // not evidence of anything about their own behavior. Excluding it is
    // what keeps this pattern from flooding SaaS Admin with false
    // positives every time a listing gets pulled.
    public function screenRapidDepositRelease(): array
    {
        $db = \Config\Database::connect();
        $candidates = $db->table('emd_hold eh')
            ->select('eh.id AS hold_id, eh.party_id, eh.sale_event_id, eh.amount, eh.held_at, eh.released_at, se.sale_format')
            ->join('sale_event se', 'se.id = eh.sale_event_id')
            ->where('eh.status', 'released')
            ->where('se.status !=', 'cancelled')
            ->where('eh.released_at IS NOT NULL')
            ->where("eh.released_at - eh.held_at < INTERVAL '" . self::RAPID_CYCLE_HOURS . " hours'", null, false)
            ->get()->getResultArray();

        $flaggedIds = [];
        foreach ($candidates as $row) {
            if ($this->flagModel->existsForHold($row['hold_id'], 'rapid_deposit_release_no_activity')) {
                continue; // already flagged on a prior scheduler run
            }

            $participated = $this->hadGenuineActivity($row['sale_event_id'], $row['party_id'], $row['sale_format']);
            if ($participated) {
                continue;
            }

            $flag = $this->createFlag('rapid_deposit_release_no_activity', $row['party_id'], $row['hold_id'], null, [
                'saleEventId' => $row['sale_event_id'],
                'saleFormat' => $row['sale_format'],
                'amount' => $row['amount'],
                'heldAt' => $row['held_at'],
                'releasedAt' => $row['released_at'],
            ]);
            $flaggedIds[] = $flag['id'];
        }
        return $flaggedIds;
    }

    // Pattern 3: "multiple accounts funding or being funded from the same
    // external bank account." Groups holds by gateway_reference — a
    // column that already existed on emd_hold but, per D-62-adjacent
    // findings, was never actually written by any code path (the payment
    // gateway itself is a dev stub, BR-52). This screens whatever
    // gateway_reference values genuinely exist; it does not fabricate
    // them. See EmdConsentController for the (clearly dev-labeled) test
    // path that lets this be exercised before a real gateway is connected.
    public function screenSharedExternalReference(): array
    {
        $db = \Config\Database::connect();
        $groups = $db->table('emd_hold')
            ->select('gateway_reference')
            ->where('gateway_reference IS NOT NULL')
            ->where("gateway_reference !=", '')
            ->groupBy('gateway_reference')
            ->having('COUNT(DISTINCT party_id) >', 1, false)
            ->get()->getResultArray();

        $flaggedIds = [];
        foreach ($groups as $group) {
            $reference = $group['gateway_reference'];
            $holds = $db->table('emd_hold')
                ->select('id, party_id, sale_event_id, amount')
                ->where('gateway_reference', $reference)
                ->get()->getResultArray();

            $partyIds = array_unique(array_column($holds, 'party_id'));
            foreach ($partyIds as $partyId) {
                if ($this->flagModel->existsForPartyAndReference($partyId, $reference)) {
                    continue; // already flagged for this exact reference
                }
                $ownHolds = array_values(array_filter($holds, fn($h) => $h['party_id'] === $partyId));
                $otherParties = array_values(array_diff($partyIds, [$partyId]));

                $flag = $this->createFlag('shared_external_reference', $partyId, $ownHolds[0]['id'] ?? null, $reference, [
                    'externalReference' => $reference,
                    'ownHoldIds' => array_column($ownHolds, 'id'),
                    'otherPartyIds' => $otherParties,
                ]);
                $flaggedIds[] = $flag['id'];
            }
        }
        return $flaggedIds;
    }

    public function runScreening(): array
    {
        return array_merge($this->screenRapidDepositRelease(), $this->screenSharedExternalReference());
    }

    // SaaS Admin's decision — BR-54: "responsible for determining whether
    // a Suspicious Transaction Report is warranted under applicable PMLA
    // obligations." $strFiled/$strReference record that determination;
    // filing itself happens through the real regulatory channel, outside
    // this platform.
    public function review(string $flagId, string $superAdminId, string $outcome, ?string $notes, bool $strFiled, ?string $strReference): array
    {
        if (!in_array($outcome, ['dismissed', 'escalated'], true)) {
            throw new \RuntimeException("Invalid AML flag outcome: {$outcome}");
        }
        $flag = $this->flagModel->find($flagId);
        if (!$flag) {
            throw new \RuntimeException('AML flag not found');
        }
        if ($flag['status'] !== 'open') {
            throw new \RuntimeException('This flag has already been reviewed.');
        }

        $this->flagModel->update($flagId, [
            'status' => $outcome,
            'reviewed_by_party_id' => $superAdminId,
            'review_notes' => $notes,
            'str_filed' => $strFiled,
            'str_reference' => $strReference,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        // BR-54/BR-05: "All flags and their resolution are logged to the
        // immutable audit trail, regardless of outcome" — logged even for
        // a dismissal, not only an escalation.
        (new AuditLogService())->log('aml.flag.reviewed', $superAdminId, [
            'flagId' => $flagId, 'patternType' => $flag['pattern_type'], 'partyId' => $flag['party_id'],
            'outcome' => $outcome, 'strFiled' => $strFiled,
        ]);

        return $this->flagModel->find($flagId);
    }

    private function hadGenuineActivity(string $saleEventId, string $partyId, string $saleFormat): bool
    {
        $db = \Config\Database::connect();
        if ($saleFormat === 'buy_now') {
            return $db->table('offer')
                ->where('sale_event_id', $saleEventId)
                ->where('buyer_party_id', $partyId)
                ->countAllResults() > 0;
        }
        // easy, express, tender all record genuine participation as a row
        // in the shared bid table.
        return $db->table('bid')
            ->where('sale_event_id', $saleEventId)
            ->where('bidder_party_id', $partyId)
            ->countAllResults() > 0;
    }

    private function createFlag(string $patternType, string $partyId, ?string $relatedEmdHoldId, ?string $externalReference, array $detail): array
    {
        $flag = $this->flagModel->createFlag([
            'pattern_type' => $patternType,
            'party_id' => $partyId,
            'related_emd_hold_id' => $relatedEmdHoldId,
            'external_reference' => $externalReference,
            'detail' => json_encode($detail),
            'status' => 'open',
        ]);

        // BR-54/BR-05: flag creation itself is logged too, not just its
        // eventual resolution — actor is null (system-triggered detection,
        // the same convention SchedulerService uses for its own summary
        // log), never the flagged party.
        (new AuditLogService())->log('aml.flag.created', null, [
            'flagId' => $flag['id'], 'patternType' => $patternType, 'partyId' => $partyId,
            'relatedEmdHoldId' => $relatedEmdHoldId, 'externalReference' => $externalReference, 'detail' => $detail,
        ]);

        return $flag;
    }
}
