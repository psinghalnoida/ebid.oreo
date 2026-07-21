<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\OfferModel;
use App\Models\SettlementModel;
use App\Models\BidModel;
use App\Libraries\ListingLifecycleService;
use App\Libraries\OfferService;
use App\Libraries\SchedulerService;

class TestScheduler extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:scheduler';
    protected $description = 'Runs the scheduled-job automation against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $offerModel = new OfferModel();
        $settlementModel = new SettlementModel();
        $bidModel = new BidModel();
        $lifecycle = new ListingLifecycleService();
        $offers = new OfferService();
        $scheduler = new SchedulerService();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Scheduler Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'schedulertest', 'buyer_fee_percent' => 5.00]);
        $seller = $partyModel->createParty('+919666601001');
        $buyer1 = $partyModel->createParty('+919666601002');
        $buyer2 = $partyModel->createParty('+919666601003');
        $buyer3 = $partyModel->createParty('+919666601004');

        CLI::write("\n=== Test 1: Grace period auto-freeze (BR-14) ===", 'yellow');
        $listing1 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600014',
        ]);
        $saleEvent1 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing1['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SCHED-001',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'status' => 'pending_approval',
        ]);
        $lifecycle->approveSaleEvent($saleEvent1['id']);
        $before = $saleEventModel->find($saleEvent1['id']);
        $this->assert($before['status'] === 'grace_period', 'Sale event correctly enters grace_period on approval');

        // Backdate grace_period_ends_at to simulate the real 60 minutes having passed
        $db->table('sale_event')->where('id', $saleEvent1['id'])->update([
            'grace_period_ends_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
        ]);

        $processed = $scheduler->processExpiredGracePeriods();
        $this->assert(in_array($saleEvent1['id'], $processed, true), 'Scheduler picked up the expired grace period');
        $after = $saleEventModel->find($saleEvent1['id']);
        $this->assert($after['status'] === 'active', 'Sale event auto-transitioned to active — no dev-force-freeze needed');

        CLI::write("\n=== Test 2: Express auto-cascades once bidding window genuinely expires ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600015',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SCHED-002',
            'sale_format' => 'express', 'reserve_value' => 40000, 'status' => 'active',
        ]);
        $express = new \App\Libraries\ExpressAuctionService();
        foreach ([$buyer1, $buyer2, $buyer3] as $b) {
            $express->pledgeReserve($saleEvent2['id'], $b['id']);
        }
        $express->placeBid($saleEvent2['id'], $buyer1['id'], 45000);
        $withStart = $saleEventModel->find($saleEvent2['id']);
        $this->assert($withStart['scheduled_start_at'] !== null, 'Bidding phase triggered on 3rd pledge, as before');

        // Backdate scheduled_end_at to simulate the real 1-hour window having passed
        $db->table('sale_event')->where('id', $saleEvent2['id'])->update([
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('-1 minute')),
        ]);

        $expressProcessed = $scheduler->processExpiredExpressBidding();
        $this->assert(in_array($saleEvent2['id'], $expressProcessed, true), 'Scheduler picked up the expired Express window');

        $ranked = $bidModel->findRankedBids($saleEvent2['id'], 1);
        $this->assert($ranked[0]['topup_required_by'] !== null, 'Cascade genuinely auto-initiated — H1 now has a real top-up deadline, with no dev-force-close-bidding call at all');

        CLI::write("\n=== Idempotency: running again does not re-trigger the same event ===", 'yellow');
        $secondRun = $scheduler->processExpiredExpressBidding();
        $this->assert(!in_array($saleEvent2['id'], $secondRun, true), 'Already-cascaded event correctly skipped on a second scheduler run');

        CLI::write("\n=== Test 3: Stale Buy-Now offer auto-lapses after 3 days ===", 'yellow');
        $listing3 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Vehicles', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600016',
        ]);
        $saleEvent3 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing3['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SCHED-003',
            'sale_format' => 'buy_now', 'expected_value' => 30000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent3['id'], $buyer1['id'], 'van', 3000);
        $offer3 = $offers->submitOffer($saleEvent3['id'], $buyer1['id'], 28000);
        $db->table('offer')->where('id', $offer3['id'])->update(['created_at' => date('Y-m-d H:i:s', strtotime('-4 days'))]);

        $lapsedIds = $scheduler->processStaleOffers();
        $this->assert(in_array($offer3['id'], $lapsedIds, true), 'Scheduler correctly lapsed the 4-day-old unactioned offer');
        $offer3After = $offerModel->find($offer3['id']);
        $this->assert($offer3After['status'] === 'lapsed', 'Offer status = lapsed, no reason required, matching the stated policy');

        CLI::write("\n=== Test 4: runAll() executes every category in one pass ===", 'yellow');
        $summary = $scheduler->runAll();
        $this->assert(array_key_exists('gracePeriodsProcessed', $summary), 'runAll() returns grace period results');
        $this->assert(array_key_exists('expressBiddingClosed', $summary), 'runAll() returns Express results');
        $this->assert(array_key_exists('staleOffersLapsed', $summary), 'runAll() returns stale offer results');
        $this->assert(array_key_exists('settlementsFlaggedStalled', $summary), 'runAll() returns settlement stall results');

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
