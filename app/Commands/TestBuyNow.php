<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Libraries\OfferService;
use App\Libraries\EmdService;

class TestBuyNow extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:buynow';
    protected $description = 'Runs the Buy-Now offer service against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $offers = new OfferService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Buy-Now Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'buynowtest']);
        $seller = $partyModel->createParty('+919777001001');
        $buyerHigh = $partyModel->createParty('+919777001002');  // will offer the most
        $buyerLow = $partyModel->createParty('+919777001003');   // will offer less, but get picked
        $buyerMid = $partyModel->createParty('+919777001004');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'make_model' => 'Test Press',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600004',
        ]);

        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BUYNOW-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        CLI::write("Sale event created: {$saleEvent['id']} EV=100000\n");

        CLI::write('=== BR-27: EMD baseline (10% of EV) ===', 'yellow');
        $baseline = EmdService::calculateBaselineEmd('buy_now', 100000, null);
        $this->assert($baseline === 10000.0, "10% of EV 100000 = {$baseline}");

        CLI::write("\n=== Place EMD holds for all 3 buyers ===", 'yellow');
        foreach ([$buyerHigh, $buyerLow, $buyerMid] as $b) {
            $emdHoldModel->createHold($saleEvent['id'], $b['id'], 'van', 10000);
        }

        CLI::write("\n=== Submit offers (no bidding, independent offers) ===", 'yellow');
        $offerHigh = $offers->submitOffer($saleEvent['id'], $buyerHigh['id'], 120000);
        $offerLow = $offers->submitOffer($saleEvent['id'], $buyerLow['id'], 95000);
        $offerMid = $offers->submitOffer($saleEvent['id'], $buyerMid['id'], 105000);
        $this->assert($offerHigh['status'] === 'submitted', 'Highest offer (120000) submitted');
        $this->assert($offerLow['status'] === 'submitted', 'Lowest offer (95000) submitted');

        CLI::write("\n=== BR-42: Accepting a NON-highest offer without a reason should fail ===", 'yellow');
        $rejected = false;
        try {
            $offers->acceptOffer($saleEvent['id'], $offerLow['id'], null);
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'BR-42');
        }
        $this->assert($rejected, 'Accepting the 95000 offer (not highest) without a reason was blocked');

        CLI::write("\n=== BR-42: Accepting a NON-highest offer WITH a reason succeeds ===", 'yellow');
        $accepted = $offers->acceptOffer($saleEvent['id'], $offerLow['id'], 'Buyer rating reflects stronger payment reliability');
        $this->assert($accepted['status'] === 'accepted', 'Lower offer accepted with a valid reason');
        $this->assert($accepted['seller_selection_reason'] !== null, 'Reason is logged on the accepted offer');

        CLI::write("\n=== Verify other offers rejected, sale event closed ===", 'yellow');
        $highAfter = (new \App\Models\OfferModel())->find($offerHigh['id']);
        $midAfter = (new \App\Models\OfferModel())->find($offerMid['id']);
        $this->assert($highAfter['status'] === 'rejected', 'Higher offer (120000) automatically rejected once seller decided');
        $this->assert($midAfter['status'] === 'rejected', 'Mid offer (105000) automatically rejected');

        $closedEvent = $saleEventModel->find($saleEvent['id']);
        $this->assert($closedEvent['status'] === 'closed_sold', 'Sale event status = closed_sold');
        $this->assert((float) $closedEvent['current_price'] === 95000.0, 'Final price reflects the ACCEPTED offer (95000), not the highest');

        CLI::write("\n=== BR-29: EMD adjustment — accepted price (95000) BELOW EV (100000) means a refund ===", 'yellow');
        $winnerHold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyerLow['id']);
        $expectedRefundedAmount = 9500.0; // 10% of 95000, down from the 10000 held against the 100000 EV
        $this->assert(
            (float) $winnerHold['recalculated_amount'] === $expectedRefundedAmount,
            "Winner's EMD recalculated to {$winnerHold['recalculated_amount']} (10% of accepted 95000 = 9500, refund of 500 owed)"
        );

        CLI::write("\n=== Losing buyers' EMD released, not forfeited ===", 'yellow');
        $highHold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyerHigh['id']);
        $midHold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyerMid['id']);
        $this->assert($highHold['status'] === 'released', 'Rejected higher-offer buyer\'s EMD released');
        $this->assert($midHold['status'] === 'released', 'Rejected mid-offer buyer\'s EMD released');

        CLI::write("\n=== BR: Offer withdrawal ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'make_model' => 'Second Item',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600005',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BUYNOW-002',
            'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $buyerHigh['id'], 'van', 5000);
        $offer2 = $offers->submitOffer($saleEvent2['id'], $buyerHigh['id'], 55000);
        $withdrawn = $offers->withdrawOffer($offer2['id'], 'Found a better item elsewhere');
        $this->assert($withdrawn['status'] === 'withdrawn', 'Offer withdrawn successfully');
        $this->assert($withdrawn['withdrawal_reason'] !== null, 'Withdrawal reason logged');
        $releasedHold = $emdHoldModel->findBySaleEventAndParty($saleEvent2['id'], $buyerHigh['id']);
        $this->assert($releasedHold['status'] === 'released', 'EMD released after withdrawing the only active offer');

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
