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
use App\Models\AmlFlagModel;
use App\Libraries\AmlMonitoringService;

class TestAml extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:aml';
    protected $description = 'Proves BR-54/PR-31 AML monitoring is real, including its two honest, currently-dormant patterns.';

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
        $flagModel = new AmlFlagModel();
        $aml = new AmlMonitoringService();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'AML Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'amltest']);
        $seller = $partyModel->createParty('+919555401001');
        $genuineBidder = $partyModel->createParty('+919555401002');
        $cyclingParty = $partyModel->createParty('+919555401003');
        $belowThresholdParty = $partyModel->createParty('+919555401004');

        $makeSaleEvent = function (string $ern) use ($listingModel, $saleEventModel, $tenant, $seller) {
            $listing = $listingModel->createListing([
                'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
                'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
                'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600070',
            ]);
            return $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => $ern,
                'sale_format' => 'easy', 'reserve_value' => 50000, 'result_mode' => 'instant_close',
            ]);
        };

        CLI::write("\n=== Pattern 1 (BR-54): deposit-then-refund cycling with NO genuine bidding ===", 'yellow');
        CLI::write('A genuine bidder who funds, bids, then is released as a loser is never flagged, no matter how many times.');
        for ($i = 1; $i <= 4; $i++) {
            $se = $makeSaleEvent("TEST-AML-GENUINE-{$i}");
            $hold = $emdHoldModel->createHold($se['id'], $genuineBidder['id'], 'van', 5000);
            $bidModel->createBid($se['id'], $genuineBidder['id'], 55000);
            $emdHoldModel->markReleased($hold['id']);
        }
        $flagged = $aml->detectDepositRefundCycles();
        $this->assert(!in_array($genuineBidder['id'], $flagged, true), 'A genuine bidder is correctly never flagged, even after 4 fund-then-release cycles');
        $this->assert(!$flagModel->hasOpenFlag($genuineBidder['id'], 'deposit_refund_cycle'), 'No flag exists for the genuine bidder');

        CLI::write("\nA party who funds and is released with ZERO participation, below the threshold, is not yet flagged.", 'yellow');
        for ($i = 1; $i <= 2; $i++) {
            $se = $makeSaleEvent("TEST-AML-BELOW-{$i}");
            $hold = $emdHoldModel->createHold($se['id'], $belowThresholdParty['id'], 'van', 5000);
            $emdHoldModel->markReleased($hold['id']);
        }
        $flagged = $aml->detectDepositRefundCycles();
        $this->assert(!in_array($belowThresholdParty['id'], $flagged, true), 'Below the 3-occurrence threshold, correctly not flagged yet');

        CLI::write("\nThe SAME pattern, repeated a 3rd time, genuinely crosses the threshold and raises a real flag.", 'yellow');
        for ($i = 1; $i <= 3; $i++) {
            $se = $makeSaleEvent("TEST-AML-CYCLE-{$i}");
            $hold = $emdHoldModel->createHold($se['id'], $cyclingParty['id'], 'van', 8000);
            $emdHoldModel->markReleased($hold['id']);
        }
        $flagged = $aml->detectDepositRefundCycles();
        $this->assert(in_array($cyclingParty['id'], $flagged, true), 'At the 3rd zero-participation cycle, a real flag is raised');
        $openFlag = $db->table('aml_flag')->where('party_id', $cyclingParty['id'])->where('flag_type', 'deposit_refund_cycle')->get()->getRowArray();
        $this->assert($openFlag !== null && $openFlag['status'] === 'open', 'The flag is a genuine row in aml_flag, status open');

        CLI::write("\nRunning detection again does NOT raise a duplicate flag for the same still-open pattern.", 'yellow');
        $aml->detectDepositRefundCycles();
        $count = $db->table('aml_flag')->where('party_id', $cyclingParty['id'])->where('flag_type', 'deposit_refund_cycle')->countAllResults();
        $this->assert($count === 1, 'Still exactly one flag, not a duplicate on a repeated scheduler run');

        $raisedLog = $db->table('audit_log')->where('event_type', 'aml.flag_raised')->like('payload', $cyclingParty['id'])->get()->getRowArray();
        $this->assert($raisedLog !== null, 'BR-05: the flag being raised is genuinely logged to the immutable audit trail');

        CLI::write("\n=== Pattern 2 (BR-54): deposits inconsistent with declared KYC profile ===", 'yellow');
        $partyWithTurnover = $partyModel->createParty('+919555401005');
        $partyModel->update($partyWithTurnover['id'], ['org_annual_turnover' => 100000]);
        $seTurnover = $makeSaleEvent('TEST-AML-KYC-001');
        $emdHoldModel->createHold($seTurnover['id'], $partyWithTurnover['id'], 'van', 80000); // 80% of declared turnover
        $flagged = $aml->detectKycInconsistentDeposits();
        $this->assert(in_array($partyWithTurnover['id'], $flagged, true), 'A deposit genuinely disproportionate to a declared turnover is flagged');

        CLI::write("\nHonest limitation, verified directly: a party with NO declared turnover (the realistic case today, since no KYC data-entry flow exists) is never flagged, however large the deposit.", 'yellow');
        $partyNoTurnover = $partyModel->createParty('+919555401006');
        $seNoTurnover = $makeSaleEvent('TEST-AML-KYC-002');
        $emdHoldModel->createHold($seNoTurnover['id'], $partyNoTurnover['id'], 'van', 900000);
        $flagged = $aml->detectKycInconsistentDeposits();
        $this->assert(!in_array($partyNoTurnover['id'], $flagged, true), 'Confirmed: with org_annual_turnover null, this pattern structurally cannot fire — not silently faked as working');

        CLI::write("\n=== Pattern 3 (BR-54): multiple accounts funded from the same external source ===", 'yellow');
        $sharedA = $partyModel->createParty('+919555401007');
        $sharedB = $partyModel->createParty('+919555401008');
        $seShared1 = $makeSaleEvent('TEST-AML-SHARED-001');
        $seShared2 = $makeSaleEvent('TEST-AML-SHARED-002');
        $holdA = $emdHoldModel->createHold($seShared1['id'], $sharedA['id'], 'van', 6000);
        $holdB = $emdHoldModel->createHold($seShared2['id'], $sharedB['id'], 'van', 6000);
        $emdHoldModel->update($holdA['id'], ['gateway_reference' => 'TEST-SHARED-REF-001']);
        $emdHoldModel->update($holdB['id'], ['gateway_reference' => 'TEST-SHARED-REF-001']);
        $flagged = $aml->detectSharedFundingSource();
        $this->assert(in_array($sharedA['id'], $flagged, true) && in_array($sharedB['id'], $flagged, true), 'When gateway_reference genuinely collides across two parties, both are flagged');

        CLI::write("\nHonest limitation, verified directly: every EMD hold created through this codebase's real call sites leaves gateway_reference NULL (the payment gateway is stubbed) — this pattern structurally cannot fire against real production data yet.", 'yellow');
        $ordinaryParty = $partyModel->createParty('+919555401009');
        $seOrdinary = $makeSaleEvent('TEST-AML-ORDINARY-001');
        $emdHoldModel->createHold($seOrdinary['id'], $ordinaryParty['id'], 'van', 9000);
        $nullRefCount = $db->table('emd_hold')->where('party_id', $ordinaryParty['id'])->where('gateway_reference', null)->countAllResults();
        $this->assert($nullRefCount === 1, 'Confirmed: a hold created via the real createHold() call path has no gateway_reference at all');

        CLI::write("\n=== SaaS Admin review flow (PR-31) ===", 'yellow');
        $superAdmin = $partyModel->createParty('+919555401010');

        try {
            $aml->reviewFlag($openFlag['id'], $superAdmin['id'], 'str_filed', null, 'Attempting to file without a reference.');
            $this->assert(false, 'Should have thrown: STR reference is mandatory when filing');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Suspicious Transaction Report'), 'Correctly rejected: filing an STR requires a reference');
        }

        $reviewed = $aml->reviewFlag($openFlag['id'], $superAdmin['id'], 'no_action', null, 'Reviewed — explainable, a known high-frequency bidder pattern.');
        $this->assert($reviewed['status'] === 'reviewed_no_action', 'The flag genuinely transitions to reviewed_no_action');
        $this->assert(!$flagModel->hasOpenFlag($cyclingParty['id'], 'deposit_refund_cycle'), 'No longer counted as open once reviewed');

        try {
            $aml->reviewFlag($openFlag['id'], $superAdmin['id'], 'no_action', null, 'Reviewing again.');
            $this->assert(false, 'Should have thrown: this flag was already reviewed');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'already been reviewed'), 'Correctly rejected: cannot re-review an already-decided flag');
        }

        $reviewedLog = $db->table('audit_log')->where('event_type', 'aml.flag_reviewed')->like('payload', $openFlag['id'])->get()->getRowArray();
        $this->assert($reviewedLog !== null, 'BR-05: the review decision is genuinely logged to the immutable audit trail, regardless of outcome');

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
