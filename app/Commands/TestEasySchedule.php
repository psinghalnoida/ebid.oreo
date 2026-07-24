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
use App\Libraries\SchedulerService;

class TestEasySchedule extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:easyschedule';
    protected $description = 'Runs Easy Auction seller-set schedule and Dynamic Time anti-sniping against real data.';

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
        $scheduler = new SchedulerService();

        CLI::write('=== Setup: an Easy Auction that has NOT started yet ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Easy Schedule Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'easyscheduletest']);
        $seller = $partyModel->createParty('+919888901001');
        $buyer = $partyModel->createParty('+919888901002');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600019',
        ]);
        $futureStart = (new \DateTimeImmutable())->modify('+1 hour');
        $futureEnd = (new \DateTimeImmutable())->modify('+2 hours');
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EASYSCHED-001',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'status' => 'active',
            'scheduled_start_at' => $futureStart->format('Y-m-d H:i:s'),
            'scheduled_end_at' => $futureEnd->format('Y-m-d H:i:s'),
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 5000);

        CLI::write("\n=== Bidding blocked before the seller-set start time ===", 'yellow');
        $blockedEarly = false;
        try {
            $easy->placeBid($saleEvent['id'], $buyer['id'], 55000);
        } catch (\RuntimeException $e) {
            $blockedEarly = str_contains($e->getMessage(), 'not started yet');
        }
        $this->assert($blockedEarly, 'Bid correctly rejected before the scheduled start time');

        CLI::write("\n=== Bidding works once within the window ===", 'yellow');
        $saleEventModel->update($saleEvent['id'], ['scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $bid1 = $easy->placeBid($saleEvent['id'], $buyer['id'], 55000);
        $this->assert((float) $bid1['amount'] === 55000.0, 'Bid succeeds once the window has opened');

        CLI::write("\n=== Bidding blocked once the schedule has genuinely ended ===", 'yellow');
        $saleEventModel->update($saleEvent['id'], ['scheduled_end_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $blockedLate = false;
        try {
            $easy->placeBid($saleEvent['id'], $buyer['id'], 60000);
        } catch (\RuntimeException $e) {
            $blockedLate = str_contains($e->getMessage(), 'closed');
        }
        $this->assert($blockedLate, 'Bid correctly rejected once the schedule has ended');

        CLI::write("\n=== Dynamic Time: a bid near the deadline pushes it back ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600021',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EASYSCHED-002',
            'sale_format' => 'easy', 'reserve_value' => 30000, 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            // D-34 correction: deadline must be within the EXTENSION amount
            // (2 min) for the corrected bid_time+extension math to actually
            // produce a later end time — 5 minutes out (the original D-32
            // test's value) is inside the WIDER 10-min increment-halving
            // window but does NOT need clock extension, since bid_time+2min
            // would still be earlier than a 5-min-out deadline.
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('+1 minute')),
            'dynamic_time_trigger_minutes' => 10, 'dynamic_time_extension_minutes' => 2,
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $buyer['id'], 'van', 3000);

        $before = $saleEventModel->find($saleEvent2['id']);
        $expectedNewEnd = (new \DateTimeImmutable())->modify('+2 minutes'); // bid time (now) + extension — the corrected formula
        $easy->placeBid($saleEvent2['id'], $buyer['id'], 32000);
        $after = $saleEventModel->find($saleEvent2['id']);

        $this->assert(
            new \DateTimeImmutable($after['scheduled_end_at']) > new \DateTimeImmutable($before['scheduled_end_at']),
            'A bid within the trigger window genuinely pushed scheduled_end_at later, not left it unchanged'
        );
        $actualNewEnd = new \DateTimeImmutable($after['scheduled_end_at']);
        $this->assert(
            abs($expectedNewEnd->getTimestamp() - $actualNewEnd->getTimestamp()) < 2,
            'The extension is (bid time + 2 min), matching the D-34-corrected formula, not (old end + 2 min)'
        );

        CLI::write("\n=== A bid NOT near the deadline does NOT trigger an extension ===", 'yellow');
        $listing3 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Vehicles', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600022',
        ]);
        $saleEvent3 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing3['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EASYSCHED-003',
            'sale_format' => 'easy', 'reserve_value' => 20000, 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            // Deadline far away — well OUTSIDE the 10-minute trigger window
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);
        $emdHoldModel->createHold($saleEvent3['id'], $buyer['id'], 'van', 2000);
        $before3 = $saleEventModel->find($saleEvent3['id']);
        $easy->placeBid($saleEvent3['id'], $buyer['id'], 22000);
        $after3 = $saleEventModel->find($saleEvent3['id']);
        $this->assert($before3['scheduled_end_at'] === $after3['scheduled_end_at'], 'A bid far from the deadline does NOT extend it — not overly aggressive');

        CLI::write("\n=== Scheduler: zero-bid Easy Auction resolves to cycle_ended_unsold, not left hanging ===", 'yellow');
        $listing4 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600023',
        ]);
        $saleEvent4 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing4['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EASYSCHED-004',
            'sale_format' => 'easy', 'reserve_value' => 15000, 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);
        $processed = $scheduler->processExpiredEasyAuctions();
        $this->assert(in_array($saleEvent4['id'], $processed, true), 'Scheduler picked up the expired, zero-bid Easy Auction');
        $closed4 = $saleEventModel->find($saleEvent4['id']);
        $this->assert($closed4['status'] === 'cycle_ended_unsold', 'Zero-bid auction correctly resolved to cycle_ended_unsold, not left active forever');

        CLI::write("\n=== Scheduler: an Easy Auction WITH bids auto-cascades once genuinely expired ===", 'yellow');
        $saleEventModel->update($saleEvent['id'], ['scheduled_end_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $scheduler->processExpiredEasyAuctions();
        $ranked = (new \App\Models\BidModel())->findRankedBids($saleEvent['id'], 1);
        $this->assert($ranked[0]['topup_required_by'] !== null, 'Cascade genuinely initiated — H1 now has a real top-up deadline (regardless of which scheduler pass caught it, since the earlier zero-bid pass may have already swept this one up too, given both had expired by then — confirmed idempotency doesn\'t double-cascade below)');

        $rankedBefore = $ranked[0]['topup_required_by'];
        $scheduler->processExpiredEasyAuctions();
        $rankedAfter = (new \App\Models\BidModel())->findRankedBids($saleEvent['id'], 1)[0]['topup_required_by'];
        $this->assert($rankedBefore === $rankedAfter, 'Running the scheduler again does NOT reset or re-trigger the top-up deadline — genuinely idempotent');

        CLI::write("\n=== Backward compatibility: a sale_event with NO schedule set still allows bidding ===", 'yellow');
        $listing5 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600024',
        ]);
        $saleEvent5 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing5['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EASYSCHED-005',
            'sale_format' => 'easy', 'reserve_value' => 10000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent5['id'], $buyer['id'], 'van', 1000);
        $bidNoSchedule = $easy->placeBid($saleEvent5['id'], $buyer['id'], 11000);
        $this->assert((float) $bidNoSchedule['amount'] === 11000.0, 'A legacy sale_event with no schedule set still allows bidding — not broken by this feature');

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
