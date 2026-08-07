<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\OfferModel;
use App\Models\SettlementModel;
use App\Models\ListingFavoriteModel;
use App\Models\RatingEventModel;
use App\Libraries\DashboardService;
use App\Libraries\AdminDirectoryService;

// D-106: proves the backend behind the 6 screens the design handoff
// flagged as having neither a mockup nor a consolidated backend --
// Buyer Dashboard, Seller Dashboard, Rating History, Star Ratings,
// Lot Directory, Trading Session Directory (docs/design/CLAUDE_DESIGN_HANDOFF.md §2).
class TestPartyDashboards extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:partydashboards';
    protected $description = 'Proves the 6 no-mockup screens: dashboards, rating history, and platform-wide admin directories (D-106).';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $bidModel = new BidModel();
        $offerModel = new OfferModel();
        $settlementModel = new SettlementModel();
        $favoriteModel = new ListingFavoriteModel();
        $ratingEventModel = new RatingEventModel();
        $dashboards = new DashboardService();
        $directory = new AdminDirectoryService();

        CLI::write('=== Setup ===', 'yellow');
        $tenantA = $tenantModel->createTenant(['name' => 'Dashboard Test Tenant A', 'tenant_class' => 'general', 'subdomain' => 'dashtesta']);
        $tenantB = $tenantModel->createTenant(['name' => 'Dashboard Test Tenant B', 'tenant_class' => 'general', 'subdomain' => 'dashtestb']);
        $seller = $partyModel->createParty('+919777801001');
        $buyer = $partyModel->createParty('+919777801002');
        $otherBuyer = $partyModel->createParty('+919777801003');

        // --- Seller-side fixtures: one active listing, one pending settlement ---
        $activeListing = $listingModel->createListing([
            'tenant_id' => $tenantA['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'subcategory' => 'Lathe', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '641001',
        ]);
        $listingModel->transitionStatus($activeListing['id'], 'active');

        $soldListing = $listingModel->createListing([
            'tenant_id' => $tenantA['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Scrap', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '641001',
        ]);
        $soldSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $soldListing['id'], 'tenant_id' => $tenantA['id'], 'ern' => 'TEST-DASH-001',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'result_mode' => 'instant_close', 'status' => 'closed_sold',
        ]);
        $settlement = $settlementModel->createSettlement($soldSaleEvent['id'], $buyer['id'], $seller['id'], 50000.0);

        // --- Buyer-side fixtures: one H1 active bid, one submitted offer ---
        $auctionListing = $listingModel->createListing([
            'tenant_id' => $tenantA['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Vehicles', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '641001',
        ]);
        $activeSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $auctionListing['id'], 'tenant_id' => $tenantA['id'], 'ern' => 'TEST-DASH-002',
            'sale_format' => 'easy', 'reserve_value' => 200000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);
        $bid = $bidModel->createBid($activeSaleEvent['id'], $buyer['id'], 210000.0);
        $bidModel->setStanding($bid['id'], 'h1');
        // an outbid bid should NOT show up as "active"
        $losingBid = $bidModel->createBid($activeSaleEvent['id'], $otherBuyer['id'], 205000.0);

        $tenderListing = $listingModel->createListing([
            'tenant_id' => $tenantA['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Electronics', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '641001',
        ]);
        $tenderSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $tenderListing['id'], 'tenant_id' => $tenantA['id'], 'ern' => 'TEST-DASH-003',
            'sale_format' => 'tender', 'expected_value' => 80000, 'result_mode' => 'approval_required', 'status' => 'active',
        ]);
        $offerModel->createOffer($tenderSaleEvent['id'], $buyer['id'], 75000.0);

        $favoriteModel->add($buyer['id'], $activeListing['id']);

        CLI::write("\n=== Buyer Dashboard: real summary, not a stub ===", 'yellow');
        $buyerSummary = $dashboards->buyerSummary($buyer['id']);
        $this->assert($buyerSummary['activeBidsCount'] >= 1, 'Active bids count includes the real H1 bid');
        $bidCategories = array_column($buyerSummary['activeBids'], 'category');
        $this->assert(in_array('Vehicles', $bidCategories, true), 'Active bids list surfaces the actual H1 bid by category');
        $this->assert($buyerSummary['openOffersCount'] >= 1, 'Open offers count includes the real submitted offer');
        $this->assert($buyerSummary['purchasesToRateCount'] >= 1, 'Purchases-to-rate count includes the unrated settlement');
        $rateCategories = array_column($buyerSummary['purchasesToRate'], 'category');
        $this->assert(in_array('Scrap', $rateCategories, true), 'Purchases-to-rate surfaces the actual unrated settlement');
        $this->assert($buyerSummary['favoriteCount'] >= 1, 'Favorite count includes the real favorite');

        // mark it rated and confirm it drops off
        $settlementModel->update($settlement['id'], ['buyer_rated_seller_at' => date('Y-m-d H:i:s')]);
        $afterRating = $dashboards->buyerSummary($buyer['id']);
        $rateCategoriesAfter = array_column($afterRating['purchasesToRate'], 'category');
        $this->assert(!in_array('Scrap', $rateCategoriesAfter, true), 'A settlement drops off "purchases to rate" once actually rated -- not a static list');

        CLI::write("\n=== Seller Dashboard: real summary ===", 'yellow');
        $sellerSummary = $dashboards->sellerSummary($seller['id']);
        $this->assert($sellerSummary['activeListingsCount'] >= 1, 'Active listings count includes the real active listing');
        $listingCategories = array_column($sellerSummary['activeListings'], 'category');
        $this->assert(in_array('Machinery', $listingCategories, true), 'Active listings list surfaces the real listing');
        $this->assert($sellerSummary['pendingSettlementsCount'] >= 1, 'Pending settlements count includes the not-yet-completed settlement');
        $this->assert($sellerSummary['payoutBankSet'] === false, 'Payout bank correctly reported as not set for a fresh party');

        // completing the settlement should drop it from pending
        $settlementModel->update($settlement['id'], ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s')]);
        $afterComplete = $dashboards->sellerSummary($seller['id']);
        $pendingIds = array_column($afterComplete['pendingSettlements'], 'id');
        $this->assert(!in_array($settlement['id'], $pendingIds, true), 'A settlement drops off "pending" once actually completed -- not a static list');
        $this->assert($afterComplete['salesThisMonthCount'] >= 1, 'Sales-this-month count reflects the real completed settlement (completed_at is this month)');

        CLI::write("\n=== Star Ratings + Rating History: reads the real audit trail ===", 'yellow');
        $freshParty = $partyModel->find($buyer['id']);
        $this->assert((float) $freshParty['star_rating'] === 3.0, 'A fresh party starts at the documented neutral 3.0');
        $this->assert((float) $freshParty['seller_star_rating'] === 3.0, 'Neutral 3.0 applies to both roles independently');

        $this->assert($ratingEventModel->findForParty($buyer['id']) === [], 'A party with no rating events yet correctly gets an empty history, not an error');

        $ratingEventModel->createEvent([
            'party_id' => $buyer['id'], 'rating_role' => 'star_rating', 'event_type' => 'upgrade',
            'previous_value' => 3.0, 'new_value' => 3.5, 'reason' => 'Clean transaction', 'status' => 'applied',
        ]);
        $ratingEventModel->createEvent([
            'party_id' => $buyer['id'], 'rating_role' => 'seller_star_rating', 'event_type' => 'downgrade',
            'previous_value' => 3.5, 'new_value' => 3.0, 'reason' => 'Late NOC', 'status' => 'pending_tenant_approval',
        ]);
        // fixture noise: a different party's events must never leak into this party's history
        $ratingEventModel->createEvent([
            'party_id' => $otherBuyer['id'], 'rating_role' => 'star_rating', 'event_type' => 'upgrade',
            'previous_value' => 3.0, 'new_value' => 3.5, 'reason' => 'Not this party', 'status' => 'applied',
        ]);

        $history = $ratingEventModel->findForParty($buyer['id']);
        $this->assert(count($history) === 2, 'Rating history returns exactly this party\'s 2 real events, no more');
        $this->assert($history[0]['created_at'] >= $history[1]['created_at'], 'History is ordered most-recent-first');
        $otherIds = array_column($history, 'party_id');
        $this->assert(!in_array($otherBuyer['id'], $otherIds, true), 'Another party\'s rating events never leak into this party\'s history');

        CLI::write("\n=== Lot Directory: platform-wide, filterable, tenant-scoped correctly ===", 'yellow');
        $tenantAListingCount = $directory->countListings(null, $tenantA['id'], null, null);
        $this->assert($tenantAListingCount >= 3, 'Tenant-scoped count includes all 3 real listings created for Tenant A');
        $tenantBListingCount = $directory->countListings(null, $tenantB['id'], null, null);
        $this->assert($tenantBListingCount === 0, 'A tenant with no listings correctly returns zero, not a leak from other tenants');

        $searchResults = $directory->findListings('Machinery', null, null, null, 50, 0);
        $searchCategories = array_column($searchResults, 'category');
        $this->assert(in_array('Machinery', $searchCategories, true), 'Free-text search matches on category');

        $activeStatusResults = $directory->findListings(null, $tenantA['id'], null, 'active', 50, 0);
        $activeStatusCategories = array_column($activeStatusResults, 'category');
        $this->assert(in_array('Machinery', $activeStatusCategories, true), 'Status filter correctly includes the active listing');
        $this->assert(!in_array('Scrap', $activeStatusCategories, true), 'Status filter correctly excludes listings still at inventory status');

        $tenderFormatResults = $directory->findListings(null, $tenantA['id'], 'tender', null, 50, 0);
        $tenderCategories = array_column($tenderFormatResults, 'category');
        $this->assert(in_array('Electronics', $tenderCategories, true), 'Sale-format filter correctly includes the tender listing');
        $this->assert(!in_array('Vehicles', $tenderCategories, true), 'Sale-format filter correctly excludes the easy-format listing');

        CLI::write("\n=== Trading Session Directory: platform-wide, filterable ===", 'yellow');
        $tenantASaleEventCount = $directory->countSaleEvents($tenantA['id'], null, null);
        $this->assert($tenantASaleEventCount >= 3, 'Tenant-scoped sale event count includes all 3 real sale events for Tenant A');
        $tenantBSaleEventCount = $directory->countSaleEvents($tenantB['id'], null, null);
        $this->assert($tenantBSaleEventCount === 0, 'A tenant with no sale events correctly returns zero');

        $activeSaleEvents = $directory->findSaleEvents($tenantA['id'], 'easy', 'active', 50, 0);
        $activeErns = array_column($activeSaleEvents, 'ern');
        $this->assert(in_array('TEST-DASH-002', $activeErns, true), 'Combined format+status filter correctly finds the real active easy-format sale event');
        $this->assert(!in_array('TEST-DASH-001', $activeErns, true), 'Combined filter correctly excludes the closed_sold sale event');

        $closedSaleEvents = $directory->findSaleEvents(null, null, 'closed_sold', 50, 0);
        $closedErns = array_column($closedSaleEvents, 'ern');
        $this->assert(in_array('TEST-DASH-001', $closedErns, true), 'Platform-wide (no tenant filter) status search still finds the real closed sale event');

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
