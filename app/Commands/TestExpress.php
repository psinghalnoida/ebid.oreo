<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Libraries\ExpressAuctionService;
use App\Libraries\CascadeService;

class TestExpress extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:express';
    protected $description = 'Runs the Express Auction service against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $express = new ExpressAuctionService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Express Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'expresstest']);
        $seller = $partyModel->createParty('+919999001001');
        $b1 = $partyModel->createParty('+919999001002');
        $b2 = $partyModel->createParty('+919999001003');
        $b3 = $partyModel->createParty('+919999001004');
        $b4 = $partyModel->createParty('+919999001005');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'make_model' => 'Test Server',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600006',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-EXPRESS-001',
            'sale_format' => 'express', 'reserve_value' => 50000, 'status' => 'active',
        ]);
        CLI::write("Sale event created: {$saleEvent['id']} RV=50000\n");

        CLI::write('=== PR-11: Bidding must NOT be open before any pledges ===', 'yellow');
        $rejected = false;
        try {
            $express->placeBid($saleEvent['id'], $b1['id'], 55000);
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'not opened yet');
        }
        $this->assert($rejected, 'Bid correctly rejected before any pledges — bidding phase not started');

        CLI::write("\n=== 1st and 2nd pledges — should NOT trigger bidding phase yet ===", 'yellow');
        $after1 = $express->pledgeReserve($saleEvent['id'], $b1['id']);
        $this->assert($express->pledgeCount($saleEvent['id']) === 1, 'Pledge count = 1 after 1st pledge');
        $this->assert($after1['scheduled_start_at'] === null, 'Bidding phase NOT triggered after 1st pledge');

        $after2 = $express->pledgeReserve($saleEvent['id'], $b2['id']);
        $this->assert($express->pledgeCount($saleEvent['id']) === 2, 'Pledge count = 2 after 2nd pledge');
        $this->assert($after2['scheduled_start_at'] === null, 'Bidding phase NOT triggered after 2nd pledge');

        CLI::write("\n=== PR-11: 3rd distinct pledge triggers the bidding phase automatically ===", 'yellow');
        $after3 = $express->pledgeReserve($saleEvent['id'], $b3['id']);
        $this->assert($express->pledgeCount($saleEvent['id']) === 3, 'Pledge count = 3 after 3rd pledge');
        $this->assert($after3['scheduled_start_at'] !== null, 'Bidding phase auto-triggered exactly on the 3rd pledge');
        $this->assert($after3['scheduled_end_at'] !== null, 'Bidding deadline set');

        CLI::write("\n=== A 4th pledge should NOT re-trigger or reset the window ===", 'yellow');
        $originalEnd = $after3['scheduled_end_at'];
        $after4 = $express->pledgeReserve($saleEvent['id'], $b4['id']);
        $this->assert($express->pledgeCount($saleEvent['id']) === 4, 'Pledge count = 4 after 4th pledge');
        $this->assert($after4['scheduled_end_at'] === $originalEnd, '4th pledge does not reset the bidding window');

        CLI::write("\n=== Bidding now open — place bids (BR-43 ceiling still enforced) ===", 'yellow');
        $bid1 = $express->placeBid($saleEvent['id'], $b1['id'], 60000);
        $this->assert((float) $bid1['amount'] === 60000.0, 'First Express bid accepted at 60000');

        $ceilingRejected = false;
        try {
            $express->placeBid($saleEvent['id'], $b2['id'], 200000);
        } catch (\RuntimeException $e) {
            $ceilingRejected = str_contains($e->getMessage(), 'BR-43');
        }
        $this->assert($ceilingRejected, 'BR-43 150% ceiling still enforced inside Express bidding');

        $bid2 = $express->placeBid($saleEvent['id'], $b2['id'], 65000);
        $this->assert((float) $bid2['amount'] === 65000.0, 'Second bid (65000) accepted, becomes new H1');

        CLI::write("\n=== Force-close the bidding window (dev-only, real window is 1hr) ===", 'yellow');
        $closed = $express->devForceCloseBidding($saleEvent['id']);
        $this->assert(new \DateTimeImmutable($closed['scheduled_end_at']) < new \DateTimeImmutable(), 'Bidding window forced into the past');

        $tooLate = false;
        try {
            $express->placeBid($saleEvent['id'], $b3['id'], 70000);
        } catch (\RuntimeException $e) {
            $tooLate = str_contains($e->getMessage(), 'closed');
        }
        $this->assert($tooLate, 'Bid correctly rejected once the 1-hour window has closed');

        CLI::write("\n=== 2-hour cascade window applies to Express (not Easy's 24hr) ===", 'yellow');
        $cascade = new CascadeService();
        $result = $cascade->initiateCascade($saleEvent['id']);
        $hoursUntilDeadline = (new \DateTimeImmutable())->diff($result['topupRequiredBy'])->h
            + ((new \DateTimeImmutable())->diff($result['topupRequiredBy'])->days * 24);
        $this->assert($hoursUntilDeadline === 2 || $hoursUntilDeadline === 1, "Express top-up window is ~2 hours (got {$hoursUntilDeadline}h) — confirms format-aware cascade window, not Easy's 24hr");

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
