<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\EmdHoldModel;
use App\Models\ChargebackCaseModel;
use App\Libraries\ChargebackService;
use App\Libraries\ConsentService;

class TestChargeback extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:chargeback';
    protected $description = 'Proves BR-52/PR-30 Chargeback Handling & Representment: evidence assembly, representment outcome, and the independent integrity-flag review that wires the rating penalty.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $bidModel = new BidModel();
        $emdHoldModel = new EmdHoldModel();
        $caseModel = new ChargebackCaseModel();
        $chargeback = new ChargebackService();
        $consent = new ConsentService();
        $db = \Config\Database::connect();

        $tenant = $tenantModel->createTenant(['name' => 'Chargeback Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'cbtest']);
        $seller = $partyModel->createParty('+919555402001');
        $buyerA = $partyModel->createParty('+919555402002');
        $buyerB = $partyModel->createParty('+919555402003');
        $superAdmin = $partyModel->createParty('+919555402004');

        $makeSaleEvent = function (string $ern) use ($listingModel, $saleEventModel, $tenant, $seller) {
            $listing = $listingModel->createListing([
                'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
                'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
                'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600070',
            ]);
            return $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => $ern,
                'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close',
            ]);
        };

        CLI::write('=== Scenario 1: ordinary chargeback (not against a forfeiture) ===', 'yellow');
        $seA = $makeSaleEvent('TEST-CB-A-001');
        $consent->recordEmdPledgeConsent($buyerA['id'], $seA['id'], 10000, 'if you default, this deposit is forfeited.', '203.0.113.5');
        $holdA = $emdHoldModel->createHold($seA['id'], $buyerA['id'], 'van', 10000);
        $bidModel->createBid($seA['id'], $buyerA['id'], 105000);

        try {
            $chargeback->fileChargeback('00000000-0000-0000-0000-000000000000', 'test');
            $this->assert(false, 'Should have thrown: EMD hold not found');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'not found'), 'Correctly rejected: filing against a non-existent EMD hold');
        }

        $caseA = $chargeback->fileChargeback($holdA['id'], 'Card statement shows an unrecognized charge.');
        $this->assert($caseA['status'] === 'represented', 'Evidence is auto-assembled and the case moves straight to represented (PR-30 step 191)');
        $this->assert($caseA['against_approved_forfeiture'] === false, 'Not flagged as against an approved forfeiture — the hold was never forfeited');

        $evidence = json_decode($caseA['evidence_package'], true);
        $this->assert($evidence['consentRecord'] !== null && str_contains($evidence['consentRecord']['consentTextShown'], '10,000'), 'Evidence genuinely includes the real, previously-recorded EMD pledge consent text');
        $this->assert(count($evidence['bidTransactionHistory']) === 1 && (float) $evidence['bidTransactionHistory'][0]['amount'] === 105000.0, 'Evidence genuinely includes the real bid history for this party on this sale event');
        $this->assert(!isset($evidence['forfeitureApprovalChain']), 'No forfeiture chain assembled — correctly omitted when the hold was never forfeited');

        $filedLog = $db->table('audit_log')->where('event_type', 'chargeback.filed')->like('payload', $caseA['id'])->get()->getRowArray();
        $this->assert($filedLog !== null, 'BR-05: filing is genuinely logged to the immutable audit trail');
        $integrityLog = $db->table('audit_log')->where('event_type', 'chargeback.against_approved_forfeiture')->like('payload', $caseA['id'])->get()->getRowArray();
        $this->assert($integrityLog === null, 'No distinct integrity-event log for an ordinary chargeback');

        $pendingIntegrity = $caseModel->findPendingIntegrityReview();
        $this->assert(!in_array($caseA['id'], array_column($pendingIntegrity, 'id'), true), 'Correctly absent from the integrity-review queue');

        CLI::write("\n=== Representment outcome (SaaS Admin records the gateway's eventual decision) ===", 'yellow');
        try {
            $chargeback->recordRepresentmentOutcome($caseA['id'], $superAdmin['id'], 'invalid', 'test');
            $this->assert(false, 'Should have thrown: invalid outcome');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Invalid representment outcome'), 'Correctly rejected: an unrecognized outcome value');
        }

        $resolved = $chargeback->recordRepresentmentOutcome($caseA['id'], $superAdmin['id'], 'won', 'Evidence package was conclusive; representment upheld by the gateway.');
        $this->assert($resolved['status'] === 'resolved_won', 'Case genuinely transitions to resolved_won');
        $resolvedLog = $db->table('audit_log')->where('event_type', 'chargeback.representment_resolved')->like('payload', $caseA['id'])->get()->getRowArray();
        $this->assert($resolvedLog !== null, 'BR-05: the representment outcome is genuinely logged');

        try {
            $chargeback->recordRepresentmentOutcome($caseA['id'], $superAdmin['id'], 'lost', 'Trying again.');
            $this->assert(false, 'Should have thrown: already resolved');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'not awaiting'), 'Correctly rejected: cannot re-decide an already-resolved case');
        }

        CLI::write("\n=== Scenario 2: chargeback filed against an already-approved forfeiture (BR-52's real concern) ===", 'yellow');
        $seB = $makeSaleEvent('TEST-CB-B-001');
        $consent->recordEmdPledgeConsent($buyerB['id'], $seB['id'], 10000, 'if you default, this deposit is forfeited.', '203.0.113.9');
        $holdB = $emdHoldModel->createHold($seB['id'], $buyerB['id'], 'van', 10000);
        $emdHoldModel->markForfeited($holdB['id'], 5000, 2000, 3000);
        $partyBBefore = $partyModel->find($buyerB['id']);
        $this->assert((float) $partyBBefore['star_rating'] === 3.0, 'Buyer B starts at the platform default 3.0★, unaffected before any review');

        $caseB = $chargeback->fileChargeback($holdB['id'], 'Disputing a charge I already agreed would be forfeited.');
        $this->assert($caseB['against_approved_forfeiture'] === true, 'Correctly flagged: this hold really was forfeited before the chargeback was filed');
        $evidenceB = json_decode($caseB['evidence_package'], true);
        $this->assert(isset($evidenceB['forfeitureApprovalChain']) && (float) $evidenceB['forfeitureApprovalChain']['forfeitedToTenantAmount'] === 5000.0, 'Evidence genuinely includes the real forfeiture allocation split');

        $integrityLogB = $db->table('audit_log')->where('event_type', 'chargeback.against_approved_forfeiture')->like('payload', $caseB['id'])->get()->getRowArray();
        $this->assert($integrityLogB !== null, 'BR-05/PR-30 step 193: a distinct account-integrity audit event is logged, independent of the representment track');

        $pendingIntegrityB = $caseModel->findPendingIntegrityReview();
        $this->assert(in_array($caseB['id'], array_column($pendingIntegrityB, 'id'), true), 'Genuinely appears in the SaaS-Admin integrity-review queue');
        $openRepresentmentB = $caseModel->findOpenRepresentment();
        $this->assert(in_array($caseB['id'], array_column($openRepresentmentB, 'id'), true), 'Also still genuinely open on the ordinary representment track — the two tracks are independent, per PR-30 step 193');

        CLI::write("\n=== Integrity review — wires the previously-dormant chargeback_against_approved_forfeiture rating penalty ===", 'yellow');
        try {
            $chargeback->reviewIntegrityFlag($caseA['id'], $superAdmin['id'], false, 'Not against a forfeiture.');
            $this->assert(false, 'Should have thrown: case A was never against an approved forfeiture');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'not filed against'), 'Correctly rejected: cannot integrity-review a case with no forfeiture behind it');
        }

        $reviewedB = $chargeback->reviewIntegrityFlag($caseB['id'], $superAdmin['id'], true, 'Confirmed: buyer explicitly consented to this exact forfeiture, chargeback is illegitimate.');
        $this->assert($reviewedB['integrity_reviewed_at'] !== null, 'Case genuinely marked reviewed');
        $this->assert($reviewedB['integrity_rating_consequence_applied'] === true, 'Rating consequence recorded as applied');

        $partyBAfter = $partyModel->find($buyerB['id']);
        $this->assert((float) $partyBAfter['star_rating'] === 1.0, 'BR-35: the -2.0 chargeback_against_approved_forfeiture penalty genuinely applied, 3.0 -> 1.0 (a previously-dormant NAMED_EVENTS category, now has a real caller)');

        $ratingLog = $db->table('rating_event')->where('party_id', $buyerB['id'])->like('reason', 'Chargeback filed against an already-approved')->get()->getRowArray();
        $this->assert($ratingLog !== null && $ratingLog['status'] === 'applied', 'A genuine rating_event row exists, self-approved at both tiers — same authority pattern as delistSellerForFraud');

        $reviewLog = $db->table('audit_log')->where('event_type', 'chargeback.integrity_reviewed')->like('payload', $caseB['id'])->get()->getRowArray();
        $this->assert($reviewLog !== null, 'BR-05: the integrity review decision is genuinely logged');

        $pendingIntegrityAfter = $caseModel->findPendingIntegrityReview();
        $this->assert(!in_array($caseB['id'], array_column($pendingIntegrityAfter, 'id'), true), 'No longer in the pending-review queue once reviewed');

        try {
            $chargeback->reviewIntegrityFlag($caseB['id'], $superAdmin['id'], true, 'Reviewing again.');
            $this->assert(false, 'Should have thrown: already reviewed');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'already been reviewed'), 'Correctly rejected: cannot re-review a case already decided');
        }

        CLI::write("\n=== Integrity review WITHOUT applying the rating consequence (a SaaS Admin's genuine discretion) ===", 'yellow');
        $buyerC = $partyModel->createParty('+919555402005');
        $seC = $makeSaleEvent('TEST-CB-C-001');
        $holdC = $emdHoldModel->createHold($seC['id'], $buyerC['id'], 'van', 10000);
        $emdHoldModel->markForfeited($holdC['id'], 5000, 2000, 3000);
        $caseC = $chargeback->fileChargeback($holdC['id'], 'Dispute test C.');
        $reviewedC = $chargeback->reviewIntegrityFlag($caseC['id'], $superAdmin['id'], false, 'Genuine gateway error, buyer already resolved directly — no consequence warranted.');
        $this->assert($reviewedC['integrity_rating_consequence_applied'] === false, 'Recorded as reviewed WITHOUT a rating consequence, a real discretionary outcome');
        $partyCAfter = $partyModel->find($buyerC['id']);
        $this->assert((float) $partyCAfter['star_rating'] === 3.0, 'Buyer C rating genuinely untouched — the SaaS Admin declined the penalty');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
