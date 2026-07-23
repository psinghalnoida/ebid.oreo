<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Libraries\TenderService;
use App\Libraries\TenderBiddingService;

class TestTenderBidding extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:tenderbidding';
    protected $description = 'Runs Tender bidding mechanics and manual EMD audit against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $tender = new TenderService();
        $bidding = new TenderBiddingService();

        CLI::write('=== Setup ===', 'yellow');
        $companyShop = $tenantModel->createTenant(['name' => 'Bidding Test Company Shop', 'tenant_class' => 'company_shop', 'subdomain' => 'tenderbidtest']);
        $seller = $partyModel->createParty('+919777501001');
        $buyer1 = $partyModel->createParty('+919777501002');
        $buyer2 = $partyModel->createParty('+919777501003');
        $notEligible = $partyModel->createParty('+919777501004');

        $listing = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600026',
        ]);
        $start = (new \DateTimeImmutable())->modify('-30 minutes');
        $end = (new \DateTimeImmutable())->modify('+30 minutes');
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TBID-001',
            'sale_format' => 'tender', 'status' => 'active',
            'scheduled_start_at' => $start->format('Y-m-d H:i:s'), 'scheduled_end_at' => $end->format('Y-m-d H:i:s'),
            'bid_increment_amount' => 10000, 'dynamic_time_trigger_minutes' => 10, 'anti_snipe_trigger_minutes' => 2,
        ]);
        $tender->grantEligibility($saleEvent['id'], $buyer1['id'], $seller['id']);
        $tender->grantEligibility($saleEvent['id'], $buyer2['id'], $seller['id']);

        CLI::write("\n=== Only eligible buyers can bid ===", 'yellow');
        $blocked = false;
        try {
            $bidding->placeBid($saleEvent['id'], $notEligible['id'], 100000);
        } catch (\RuntimeException $e) {
            $blocked = str_contains($e->getMessage(), 'approved as eligible');
        }
        $this->assert($blocked, 'A non-eligible party is correctly blocked from bidding');

        CLI::write("\n=== Manual EMD audit trail — with a real amount ===", 'yellow');
        $tender->logManualEmd($saleEvent['id'], $buyer1['id'], 5000, 'NEFT to shop account, ref #TX992', null, $seller['id']);
        $log1 = $tender->getEmdLog($saleEvent['id']);
        $this->assert($log1[0]['amount'] == 5000, 'Real EMD amount logged correctly');

        $missingLocation = false;
        try {
            $tender->logManualEmd($saleEvent['id'], $buyer2['id'], 3000, null, null, $seller['id']);
        } catch (\RuntimeException $e) {
            $missingLocation = str_contains($e->getMessage(), 'location');
        }
        $this->assert($missingLocation, 'A real amount without a location note is correctly rejected');

        CLI::write("\n=== Manual EMD audit trail — waived, with a mandatory reason ===", 'yellow');
        $tender->logManualEmd($saleEvent['id'], $buyer2['id'], 0, null, 'Waived by insurer per Terms of Sale clause 4.2', $seller['id']);
        $log2 = $tender->getEmdLog($saleEvent['id']);
        $this->assert(count($log2) === 2, 'Both EMD entries logged');

        $notEligibleForEmd = false;
        try {
            $tender->logManualEmd($saleEvent['id'], $notEligible['id'], 0, null, null, $seller['id']);
        } catch (\RuntimeException $e) {
            $notEligibleForEmd = true;
        }
        $this->assert($notEligibleForEmd, 'Cannot log EMD for a non-eligible party at all');

        CLI::write("\n=== Bidding: increment enforced ===", 'yellow');
        $bid1 = $bidding->placeBid($saleEvent['id'], $buyer1['id'], 100000);
        $this->assert((float) $bid1['amount'] === 100000.0, 'First bid accepted');

        $tooSmall = false;
        try {
            $bidding->placeBid($saleEvent['id'], $buyer2['id'], 105000);
        } catch (\RuntimeException $e) {
            $tooSmall = str_contains($e->getMessage(), 'minimum increment');
        }
        $this->assert($tooSmall, 'A bid below the required increment (10000) is correctly rejected');

        $bid2 = $bidding->placeBid($saleEvent['id'], $buyer2['id'], 110000);
        $this->assert((float) $bid2['amount'] === 110000.0, 'A bid meeting the exact increment succeeds');

        CLI::write("\n=== Increment halving: 10 min before scheduled end ===", 'yellow');
        $nearEnd = (new \DateTimeImmutable())->modify('+5 minutes');
        $saleEventModel->update($saleEvent['id'], ['scheduled_end_at' => $nearEnd->format('Y-m-d H:i:s')]);

        $before = $saleEventModel->find($saleEvent['id']);
        $this->assert((float) $before['bid_increment_amount'] === 10000.0, 'Increment still 10000 before entering the window');

        $bid3 = $bidding->placeBid($saleEvent['id'], $buyer1['id'], 120000);
        $afterHalving = $saleEventModel->find($saleEvent['id']);
        $this->assert((float) $afterHalving['bid_increment_amount'] === 5000.0, 'Increment correctly halved to 5000 once inside the 10-min window');
        $this->assert($afterHalving['increment_halved_at'] !== null, 'increment_halved_at timestamp recorded');

        CLI::write("\n=== Increment halves ONCE, not repeatedly ===", 'yellow');
        $bid4 = $bidding->placeBid($saleEvent['id'], $buyer2['id'], 125000);
        $afterSecondBid = $saleEventModel->find($saleEvent['id']);
        $this->assert((float) $afterSecondBid['bid_increment_amount'] === 5000.0, 'Increment stayed at 5000 — did not halve again');

        CLI::write("\n=== Anti-snipe extension: bid-time + extension math, matching the worked example exactly ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Vehicles', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600027',
        ]);
        // Simulate: current end is "11:00:00" (fixed reference point).
        // A bid lands at "10:59:40" — 20 seconds before end.
        $fixedEnd = new \DateTimeImmutable('2026-01-01 11:00:00');
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TBID-002',
            'sale_format' => 'tender', 'status' => 'active',
            'scheduled_start_at' => '2026-01-01 10:00:00', 'scheduled_end_at' => $fixedEnd->format('Y-m-d H:i:s'),
            'anti_snipe_trigger_minutes' => 2, 'dynamic_time_extension_minutes' => 2,
        ]);
        $tender->grantEligibility($saleEvent2['id'], $buyer1['id'], $seller['id']);

        // Directly test the extension logic with a controlled "bid time"
        // by manipulating scheduled_end_at to simulate the exact scenario,
        // then invoking the extension check as if "now" were 10:59:40 —
        // done via a real time-travel-free approach: set current end to
        // 20 seconds from now, matching "20 seconds before end" exactly.
        $simulatedEnd = (new \DateTimeImmutable())->modify('+20 seconds');
        $saleEventModel->update($saleEvent2['id'], ['scheduled_end_at' => $simulatedEnd->format('Y-m-d H:i:s')]);
        $expectedNewEnd = (new \DateTimeImmutable())->modify('+2 minutes'); // "now" (bid time) + 2 min extension

        $bidding->applyAntiSnipeExtensionIfNeeded($saleEvent2['id']);
        $afterExtension = $saleEventModel->find($saleEvent2['id']);
        $actualNewEnd = new \DateTimeImmutable($afterExtension['scheduled_end_at']);
        $diffSeconds = abs($expectedNewEnd->getTimestamp() - $actualNewEnd->getTimestamp());
        $this->assert($diffSeconds <= 2, "New end time is (bid time + 2 min), not (old end + 2 min) — diff was {$diffSeconds}s, expected ~0s");

        CLI::write("\n=== Anti-snipe boundary case: bid lands exactly at the 2-min edge — no extension needed ===", 'yellow');
        $listing3 = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600028',
        ]);
        $saleEvent3 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing3['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TBID-003',
            'sale_format' => 'tender', 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            // End time is EXACTLY 2 minutes from now — right at the boundary
            'scheduled_end_at' => (new \DateTimeImmutable())->modify('+2 minutes')->format('Y-m-d H:i:s'),
            'anti_snipe_trigger_minutes' => 2, 'dynamic_time_extension_minutes' => 2,
        ]);
        $endBefore = $saleEventModel->find($saleEvent3['id'])['scheduled_end_at'];
        $bidding->applyAntiSnipeExtensionIfNeeded($saleEvent3['id']);
        $endAfter = $saleEventModel->find($saleEvent3['id'])['scheduled_end_at'];
        $this->assert($endBefore === $endAfter, 'At the exact 2-minute boundary, end time is correctly left unchanged — confirmed rule');

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
