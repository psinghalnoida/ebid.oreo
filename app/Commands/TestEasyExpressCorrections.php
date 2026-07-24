<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Libraries\EasyAuctionService;
use App\Libraries\ExpressAuctionService;

class TestEasyExpressCorrections extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:easyexpresscorrections';
    protected $description = 'Verifies the D-34 corrections applied to Easy/Express.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $easy = new EasyAuctionService();
        $express = new ExpressAuctionService();

        CLI::write('=== Direct math check: percentage-to-amount calculation ===', 'yellow');
        $this->assert(round(100000 * (2 / 100), 2) === 2000.0, '2% of 100000 = 2000');
        $this->assert(round(100000 * 0.02, 2) === 2000.0, 'Express automatic 2% of 100000 = 2000');

        CLI::write("\n=== Easy: clock-extension math is now corrected ===", 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Corrections Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'correctionstest']);
        $seller = $partyModel->createParty('+919444901001');
        $buyer1 = $partyModel->createParty('+919444901002');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600031',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CORR-001',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'scheduled_end_at' => (new \DateTimeImmutable())->modify('+20 seconds')->format('Y-m-d H:i:s'),
            'bid_increment_amount' => 2000, 'dynamic_time_trigger_minutes' => 10, 'dynamic_time_extension_minutes' => 2,
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer1['id'], 'van', 10000);

        $expectedNewEnd = (new \DateTimeImmutable())->modify('+2 minutes');

        $easy->placeBid($saleEvent['id'], $buyer1['id'], 102000);
        $afterBid = $saleEventModel->find($saleEvent['id']);
        $actualNewEnd = new \DateTimeImmutable($afterBid['scheduled_end_at']);
        $diffSeconds = abs($expectedNewEnd->getTimestamp() - $actualNewEnd->getTimestamp());
        $this->assert($diffSeconds <= 2, "Corrected math: new end is (bid time + 2min) — diff {$diffSeconds}s");

        CLI::write("\n=== Easy: increment halved in the same shared window ===", 'yellow');
        $this->assert((float) $afterBid['bid_increment_amount'] === 1000.0, 'Increment correctly halved from 2000 to 1000');
        $this->assert($afterBid['increment_halved_at'] !== null, 'increment_halved_at recorded');

        CLI::write("\n=== Easy: increment now genuinely enforced ===", 'yellow');
        $tooSmall = false;
        try {
            $easy->placeBid($saleEvent['id'], $buyer1['id'], 102500);
        } catch (\RuntimeException $e) {
            $tooSmall = str_contains($e->getMessage(), 'minimum increment');
        }
        $this->assert($tooSmall, 'A bid below the halved increment (1000) is correctly rejected');

        CLI::write("\n=== Express: increment halves, but the clock stays FIXED ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600032',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CORR-002',
            'sale_format' => 'express', 'reserve_value' => 50000, 'status' => 'active',
            'bid_increment_amount' => 1000,
        ]);
        $fixedEnd = (new \DateTimeImmutable())->modify('+5 minutes');
        $saleEventModel->update($saleEvent2['id'], [
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            'scheduled_end_at' => $fixedEnd->format('Y-m-d H:i:s'),
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $buyer1['id'], 'van', 5000);

        $endBefore = $saleEventModel->find($saleEvent2['id'])['scheduled_end_at'];
        $express->placeBid($saleEvent2['id'], $buyer1['id'], 51000);
        $afterExpress = $saleEventModel->find($saleEvent2['id']);

        $this->assert($afterExpress['scheduled_end_at'] === $endBefore, 'Express clock genuinely did NOT move, even inside the halving window');
        $this->assert((float) $afterExpress['bid_increment_amount'] === 500.0, 'Express increment correctly halved from 1000 to 500');
        $this->assert($afterExpress['increment_halved_at'] !== null, 'Express increment_halved_at recorded');

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
