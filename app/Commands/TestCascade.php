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
use App\Libraries\EmdService;
use App\Libraries\BiddingService;
use App\Libraries\CascadeService;

class TestCascade extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:cascade';
    protected $description = 'Runs the EMD engine and cascade logic against real data.';

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
        $biddingService = new BiddingService();
        $cascadeService = new CascadeService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => 'Cascade Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'cascadetest-php', 'buyer_fee_percent' => 5.00,
        ]);

        $seller = $partyModel->createParty('+919000001001');
        $buyer1 = $partyModel->createParty('+919000001002'); // will become H1
        $buyer2 = $partyModel->createParty('+919000001003'); // will become H2
        $buyer3 = $partyModel->createParty('+919000001004'); // will become H3

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Industrial Plant',
            'quantity' => 1, 'quantity_basis' => 'unit', 'make_model' => 'Test Loader',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600001',
        ]);

        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CASCADE-PHP-001',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close',
        ]);
        $saleEventModel->transitionStatus($saleEvent['id'], 'active');
        CLI::write("Sale event created: {$saleEvent['id']} RV=100000\n");

        CLI::write('=== BR-27: EMD baseline calculation ===', 'yellow');
        $baseline = EmdService::calculateBaselineEmd('easy', null, 100000);
        $this->assert($baseline === 10000.0, "10% of RV 100000 = {$baseline}");

        CLI::write("\n=== Place EMD holds for all 3 bidders (BR-25/27) ===", 'yellow');
        foreach ([$buyer1, $buyer2, $buyer3] as $buyer) {
            $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 10000);
        }
        CLI::write('  3 separate, segregated EMD holds created (not pooled)');

        CLI::write("\n=== Bidding (BR-43 anti-jacking, BR-27 EMD gate) ===", 'yellow');
        $bid1 = $biddingService->placeBid($saleEvent['id'], $buyer1['id'], 120000);
        $bid2 = $biddingService->placeBid($saleEvent['id'], $buyer2['id'], 130000);
        $bid3 = $biddingService->placeBid($saleEvent['id'], $buyer3['id'], 140000);
        $this->assert((float) $bid3['amount'] === 140000.0, 'H1 bid (buyer3) is 140000');

        $rejected = false;
        try {
            $biddingService->placeBid($saleEvent['id'], $buyer1['id'], 250000);
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'BR-43');
        }
        $this->assert($rejected, 'BR-43: bid exceeding 150% of current high (250000 > 210000) was rejected');

        CLI::write("\n=== Verify standings after bidding ===", 'yellow');
        $ranked = $bidModel->findRankedBids($saleEvent['id'], 3);
        $this->assert($ranked[0]['bidder_party_id'] === $buyer3['id'], 'H1 = buyer3 (140000)');
        $this->assert($ranked[1]['bidder_party_id'] === $buyer2['id'], 'H2 = buyer2 (130000)');
        $this->assert($ranked[2]['bidder_party_id'] === $buyer1['id'], 'H3 = buyer1 (120000)');

        CLI::write("\n=== BR-28: Full cascade failure scenario ===", 'yellow');
        $step1 = $cascadeService->initiateCascade($saleEvent['id']);
        $this->assert($step1['bidId'] === $ranked[0]['id'], 'Top-up window opened for H1 (buyer3)');

        CLI::write('H1 (buyer3) defaults...');
        $afterH1 = $cascadeService->processDefault($saleEvent['id'], $ranked[0]['id'], 5.00);
        $this->assert($afterH1['outcome'] === 'baton_passed', 'Baton passed after H1 default');
        $this->assert($afterH1['newTopHolderPartyId'] === $buyer2['id'], 'Baton passed to H2 (buyer2)');
        $this->assert(
            (float) $afterH1['forfeitedHold']['forfeited_to_seller_amount'] === 9450.0,
            "H1 default (non-cascade-failure yet) — seller gets standard share: {$afterH1['forfeitedHold']['forfeited_to_seller_amount']}"
        );

        CLI::write("\nH2 (buyer2) defaults...");
        $afterH2 = $cascadeService->processDefault($saleEvent['id'], $ranked[1]['id'], 5.00);
        $this->assert($afterH2['outcome'] === 'baton_passed', 'Baton passed after H2 default');
        $this->assert($afterH2['newTopHolderPartyId'] === $buyer1['id'], 'Baton passed to H3 (buyer1)');

        CLI::write("\nH3 (buyer1) ALSO defaults — full cascade failure...");
        $afterH3 = $cascadeService->processDefault($saleEvent['id'], $ranked[2]['id'], 5.00);
        $this->assert($afterH3['outcome'] === 'full_cascade_failure', 'Full cascade failure detected on 3rd default');
        $this->assert(
            (float) $afterH3['forfeitedHold']['forfeited_to_seller_amount'] === 0.0,
            'BR-28: seller receives ZERO share on full cascade failure'
        );
        $totalToPlatform = (float) $afterH3['forfeitedHold']['forfeited_to_tenant_amount'] + (float) $afterH3['forfeitedHold']['forfeited_to_saas_amount'];
        $this->assert($totalToPlatform === 10000.0, 'BR-28: full forfeited amount (10000) retained entirely by platform (tenant+saas)');

        $closedEvent = $saleEventModel->find($saleEvent['id']);
        $this->assert($closedEvent['status'] === 'cancelled', 'BR-28: sale_event status = cancelled after full cascade failure');

        CLI::write("\n=== BR-34: Standard forfeiture math check ===", 'yellow');
        $standard = EmdService::calculateForfeitureAllocation(10000, 5.00, 0.5, false);
        $this->assert($standard['tenantAmount'] === 500.0, 'Tenant gets 5% of 10000 = 500');
        $this->assert($standard['saasAmount'] === 50.0, 'SaaS gets 0.5% of 10000 = 50');
        $this->assert($standard['sellerAmount'] === 9450.0, 'Seller gets remainder = 9450');
        $sum = $standard['tenantAmount'] + $standard['saasAmount'] + $standard['sellerAmount'];
        $this->assert($sum === 10000.0, 'Standard allocation sums to exactly the forfeited amount');

        CLI::write("\n=== BR-28: Recalculation to closing value ===", 'yellow');
        $owed = EmdService::calculateCascadeTopupOwed(10000, 140000);
        $this->assert($owed === 4000.0, "H1 held 10000, closed at 140000, owes top-up of {$owed}");

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
