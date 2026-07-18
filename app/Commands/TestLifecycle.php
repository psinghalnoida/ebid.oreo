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
use App\Libraries\ListingLifecycleService;
use App\Libraries\BiddingService;

class TestLifecycle extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:lifecycle';
    protected $description = 'Runs the listing lifecycle service against real data.';

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
        $lifecycle = new ListingLifecycleService();
        $bidding = new BiddingService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Lifecycle Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'lifecycletest']);
        $seller = $partyModel->createParty('+919222001001');
        $buyer = $partyModel->createParty('+919222001002');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery',
            'quantity' => 1, 'quantity_basis' => 'unit', 'make_model' => 'Test Press',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600002',
        ]);
        $this->assert($listing['status'] === 'inventory', 'BR-13: new listing starts at inventory');

        CLI::write("\n=== BR-13: Approval lifecycle ===", 'yellow');
        $listing = $lifecycle->submitForApproval($listing['id']);
        $this->assert($listing['status'] === 'pending_approval', 'Submitted for approval');

        $rejected = $lifecycle->reject($listing['id'], 'insufficient photos');
        $this->assert($rejected['status'] === 'inventory', 'Rejected listing returns to inventory');
        $this->assert($rejected['rejection_reason'] === 'insufficient photos', 'Rejection reason logged');

        $listing = $lifecycle->submitForApproval($listing['id']);
        $listing = $lifecycle->approve($listing['id']);
        $this->assert($listing['status'] === 'upcoming', 'Approved listing moves to upcoming');

        CLI::write("\n=== BR-14: Sale event grace period (Easy format) ===", 'yellow');
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-LIFECYCLE-001',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'result_mode' => 'instant_close', 'status' => 'pending_approval',
        ]);
        $listingModel->transitionStatus($listing['id'], 'active');

        $approved = $lifecycle->approveSaleEvent($saleEvent['id']);
        $this->assert($approved['status'] === 'grace_period', 'Easy format enters grace_period on approval');
        $this->assert($approved['grace_period_ends_at'] !== null, 'Grace period end time set');

        $edited = $lifecycle->editWithinGrace($saleEvent['id'], ['reserve_value' => 55000]);
        $this->assert((float) $edited['reserve_value'] === 55000.0, 'BR-14: direct edit within grace window applied');
        $this->assert($edited['grace_period_ends_at'] >= $approved['grace_period_ends_at'], 'BR-14/PR-20: editing resets the 60-minute clock');

        CLI::write("\n=== BR-14: Express format — no grace window at all ===", 'yellow');
        $expressListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Vehicles',
            'quantity' => 1, 'quantity_basis' => 'unit', 'make_model' => 'Test Van',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600003',
        ]);
        $listingModel->transitionStatus($expressListing['id'], 'active');
        $expressEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $expressListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-LIFECYCLE-EXPRESS-001',
            'sale_format' => 'express', 'reserve_value' => 30000, 'status' => 'pending_approval',
        ]);
        $approvedExpress = $lifecycle->approveSaleEvent($expressEvent['id']);
        $this->assert($approvedExpress['status'] === 'active', 'BR-14: Express skips grace period, goes straight to active');
        $this->assert($approvedExpress['grace_period_ends_at'] === null, 'Express has no grace_period_ends_at set');

        CLI::write("\n=== BR-13/BR-14: Material edit on ACTIVE listing (archive-and-recreate) ===", 'yellow');
        // Freeze the easy sale_event to active first, place a bid+EMD to prove cancellation/refund
        $saleEventModel->update($saleEvent['id'], ['status' => 'active', 'grace_period_ends_at' => null]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 5500);
        $bid = $bidding->placeBid($saleEvent['id'], $buyer['id'], 60000);
        $this->assert($bid['standing'] === 'h1', 'Bid placed and holds H1 before the material edit');

        $result = $lifecycle->requestMaterialEdit($listing['id'], [
            'physical_condition' => 'Refurbished', 'category' => 'Machinery',
            'quantity' => 1, 'quantity_basis' => 'unit', 'make_model' => 'Test Press (Refurbished)',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600002',
        ]);
        $this->assert($result['archivedOriginal']['archived_at'] !== null, 'BR-13: original listing archived, not deleted');
        $this->assert($result['archivedOriginal']['superseded_by_listing_id'] === $result['newListing']['id'], 'Archived listing points to its replacement');
        $this->assert($result['newListing']['status'] === 'upcoming', 'BR-13: new listing re-enters lifecycle at upcoming');

        $cancelledEvent = $saleEventModel->find($saleEvent['id']);
        $this->assert($cancelledEvent['status'] === 'cancelled', 'BR-14: the active sale_event was cancelled by the material edit');

        $cancelledBid = $bidModel->find($bid['id']);
        $this->assert($cancelledBid['standing'] === 'withdrawn', 'BR-14: bid was withdrawn, never silently migrated');

        $releasedHold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyer['id']);
        $this->assert($releasedHold['status'] === 'released', 'BR-14: buyer\'s EMD was released, not left held or forfeited');

        CLI::write("\n=== BR-14: Emergency stop ===", 'yellow');
        $stopped = $lifecycle->emergencyStop($expressEvent['id'], 'Suspected fraudulent listing — Tenant Admin review');
        $this->assert($stopped['status'] === 'cancelled', 'Emergency stop cancels the event regardless of format');
        $this->assert($stopped['emergency_stop_reason'] !== null, 'Mandatory audited reason logged');

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
