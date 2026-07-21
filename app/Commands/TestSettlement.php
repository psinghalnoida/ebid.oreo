<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\SettlementModel;
use App\Libraries\OfferService;
use App\Libraries\SettlementService;
use App\Libraries\EmdService;

class TestSettlement extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:settlement';
    protected $description = 'Runs the settlement/dual-NOC/rating gate and stall resolution against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $settlementModel = new SettlementModel();
        $offers = new OfferService();
        $settlement = new SettlementService();

        CLI::write('=== Setup: Buy-Now sale that reaches closed_sold ===', 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => 'Settlement Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'settlementtest', 'buyer_fee_percent' => 5.00,
        ]);
        $seller = $partyModel->createParty('+919888801001');
        $buyer = $partyModel->createParty('+919888801002');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600007',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SETTLE-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 10000);
        $offer = $offers->submitOffer($saleEvent['id'], $buyer['id'], 95000);

        CLI::write("\n=== BR-33: Settlement auto-created on offer acceptance ===", 'yellow');
        $offers->acceptOffer($saleEvent['id'], $offer['id'], null);
        $s = $settlementModel->findBySaleEvent($saleEvent['id']);
        $this->assert($s !== null, 'Settlement record created automatically');
        $this->assert($s['status'] === 'pending', 'Settlement starts pending');
        $this->assert((float) $s['final_price'] === 95000.0, 'Settlement final price matches accepted offer');

        CLI::write("\n=== BR-33: All 4 steps required — nothing completes with only some done ===", 'yellow');
        $settlement->confirmSellerNoc($s['id'], $seller['id']);
        $afterOne = $settlementModel->find($s['id']);
        $this->assert($afterOne['status'] === 'pending', 'Still pending after only 1 of 4 steps');

        $settlement->confirmBuyerNoc($s['id'], $buyer['id']);
        $afterTwo = $settlementModel->find($s['id']);
        $this->assert($afterTwo['status'] === 'pending', 'Still pending after 2 of 4 steps');

        CLI::write("\n=== Wrong party cannot confirm the other side's NOC ===", 'yellow');
        $rejected = false;
        try {
            $settlement->confirmBuyerNoc($s['id'], $seller['id']); // seller trying to confirm BUYER's noc
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'BR-33');
        }
        $this->assert($rejected, 'Seller cannot confirm the buyer\'s NOC on their behalf');

        CLI::write("\n=== Ratings: 'good' outcome upgrades automatically ===", 'yellow');
        $sellerBefore = $partyModel->find($seller['id']);
        $settlement->submitRating($s['id'], $buyer['id'], 'buyer', 'good');
        $sellerAfter = $partyModel->find($seller['id']);
        $this->assert(
            (float) $sellerAfter['seller_star_rating'] > (float) $sellerBefore['seller_star_rating'],
            "Seller's rating increased immediately after a 'good' buyer rating ({$sellerBefore['seller_star_rating']} -> {$sellerAfter['seller_star_rating']})"
        );

        $afterThree = $settlementModel->find($s['id']);
        $this->assert($afterThree['status'] === 'pending', 'Still pending after 3 of 4 steps');

        CLI::write("\n=== 4th step completes the settlement AND deducts the fee ===", 'yellow');
        $settlement->submitRating($s['id'], $seller['id'], 'seller', 'good');
        $completed = $settlementModel->find($s['id']);
        $this->assert($completed['status'] === 'completed', 'Settlement status = completed after all 4 steps');
        $this->assert($completed['completed_at'] !== null, 'completed_at timestamp set');

        $hold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyer['id']);
        $this->assert($hold['status'] === 'released', 'Buyer\'s EMD hold marked released');
        // 5% of 95000 = 4750 fee; buyer held 10000; refund = 10000 - 4750 = 5250
        $this->assert((float) $hold['recalculated_amount'] === 5250.0, "Buyer refund correctly calculated: {$hold['recalculated_amount']} (expected 5250)");
        $this->assert(
            (float) $hold['forfeited_to_tenant_amount'] + (float) $hold['forfeited_to_saas_amount'] === 4750.0,
            'Fee split (tenant+saas) sums to the correct total (4750)'
        );

        CLI::write("\n=== BR-42: EmdService.calculateSettlementFee direct math check ===", 'yellow');
        $fees = EmdService::calculateSettlementFee(95000, 5.00, 10000, 0.5);
        $this->assert($fees['saasAmount'] === 475.0, 'SaaS gets 0.5% of 95000 = 475');
        $this->assert($fees['tenantAmount'] === 4275.0, 'Tenant gets the remainder of the 5% fee = 4275');
        $this->assert($fees['buyerRefund'] === 5250.0, 'Buyer refund = 10000 - 4750 = 5250');

        CLI::write("\n=== BR-39: Stall resolution — a settlement that never gets rated ===", 'yellow');
        $buyer2 = $partyModel->createParty('+919888801003');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600008',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SETTLE-002',
            'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $buyer2['id'], 'van', 5000);
        $offer2 = $offers->submitOffer($saleEvent2['id'], $buyer2['id'], 48000);
        $offers->acceptOffer($saleEvent2['id'], $offer2['id'], null);
        $s2 = $settlementModel->findBySaleEvent($saleEvent2['id']);

        // Backdate created_at directly to simulate 8 days passing (beyond the 7-day threshold)
        $db = \Config\Database::connect();
        $db->table('settlement')->where('id', $s2['id'])->update(['created_at' => date('Y-m-d H:i:s', strtotime('-8 days'))]);

        $flagged = $settlement->flagStalledSettlements();
        $this->assert(in_array($s2['id'], $flagged, true), 'Stale settlement correctly flagged after 7+ days incomplete');

        $s2AfterFlag = $settlementModel->find($s2['id']);
        $this->assert($s2AfterFlag['status'] === 'stalled', 'Status transitioned to stalled');

        CLI::write("\n=== BR-39: Force-resolving a stalled settlement applies forced-neutral ===", 'yellow');
        $buyer2Before = $partyModel->find($buyer2['id']);
        $sellerBefore2 = $partyModel->find($seller['id']);

        $resolved = $settlement->forceResolveStalled($s2['id']);
        $this->assert($resolved['status'] === 'completed', 'Force-resolved settlement reaches completed status');
        $this->assert($resolved['forced_neutral_applied_at'] !== null, 'forced_neutral_applied_at timestamp recorded');

        $sellerAfter2 = $partyModel->find($seller['id']);
        $this->assert(
            (float) $sellerAfter2['seller_star_rating'] === 3.0,
            "Seller's rating forced to exactly 3.0 (was never rated by the buyer): {$sellerAfter2['seller_star_rating']}"
        );

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
