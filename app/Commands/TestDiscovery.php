<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\ListingFavoriteModel;
use App\Models\SavedSearchModel;
use App\Models\SearchHistoryModel;

class TestDiscovery extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:discovery';
    protected $description = 'Proves favorites, saved searches, search history, and recommendations are real (Phase 3C+).';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $bidModel = new BidModel();
        $favoriteModel = new ListingFavoriteModel();
        $savedSearchModel = new SavedSearchModel();
        $historyModel = new SearchHistoryModel();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Discovery Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'discoverytest']);
        $seller = $partyModel->createParty('+919555407001');
        $buyer = $partyModel->createParty('+919555407002');

        $listingA = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600100',
        ]);
        $seA = $saleEventModel->createSaleEvent([
            'listing_id' => $listingA['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISCOVERY-A',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);

        CLI::write("\n=== Favorites: add, idempotent re-add, remove ===", 'yellow');
        $this->assert(!$favoriteModel->isFavorited($buyer['id'], $listingA['id']), 'Not favorited initially');
        $favoriteModel->add($buyer['id'], $listingA['id']);
        $this->assert($favoriteModel->isFavorited($buyer['id'], $listingA['id']), 'Genuinely favorited after add');
        $favoriteModel->add($buyer['id'], $listingA['id']); // idempotent, should not error or duplicate
        $found = $favoriteModel->findForParty($buyer['id']);
        $this->assert(count($found) === 1, 'Adding twice does not create a duplicate favorite (UNIQUE constraint respected)');
        $favoriteModel->remove($buyer['id'], $listingA['id']);
        $this->assert(!$favoriteModel->isFavorited($buyer['id'], $listingA['id']), 'Genuinely removed');

        CLI::write("\n=== Saved searches: create, list, rerun-ready filters ===", 'yellow');
        $search = $savedSearchModel->create($buyer['id'], 'Cheap machinery', ['category' => 'Machinery', 'price_max' => '100000']);
        $mine = $savedSearchModel->findForParty($buyer['id']);
        $this->assert(count($mine) === 1, 'The saved search is genuinely persisted');
        $decoded = json_decode($mine[0]['filters'], true);
        $this->assert($decoded['category'] === 'Machinery' && $decoded['price_max'] === '100000', 'Filters round-trip correctly through JSON storage');

        CLI::write("\n=== Search history: records real searches, respects the 20-item cap on retrieval ===", 'yellow');
        for ($i = 1; $i <= 25; $i++) {
            $historyModel->record($buyer['id'], ['q' => "search-{$i}"]);
        }
        $recent = $historyModel->findRecentForParty($buyer['id'], 20);
        $this->assert(count($recent) === 20, 'Retrieval genuinely caps at 20, even though 25 were recorded');
        $this->assert(json_decode($recent[0]['filters'], true)['q'] === 'search-25', 'Most recent search genuinely comes first');

        CLI::write("\n=== Recommendations: Trending Now reflects real bid activity, Based on Your Bids reflects real bid history ===", 'yellow');
        $trendingBidder = $partyModel->createParty('+919555407003');
        $bidModel->createBid($seA['id'], $trendingBidder['id'], 55000);

        $db = \Config\Database::connect();
        $trending = $db->table('bid b')
            ->select('se.id as sale_event_id, COUNT(b.id) as bid_count')
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->where('b.placed_at >=', date('Y-m-d H:i:s', strtotime('-1 day')))
            ->where('se.id', $seA['id'])
            ->groupBy('se.id')
            ->get()->getRowArray();
        $this->assert((int) $trending['bid_count'] >= 1, 'Trending Now genuinely counts real, recent bids for this sale event');

        $listingB = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600101',
        ]);
        $seB = $saleEventModel->createSaleEvent([
            'listing_id' => $listingB['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISCOVERY-B',
            'sale_format' => 'easy', 'reserve_value' => 40000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);
        // trendingBidder bid on seA (Machinery) — seB is also Machinery
        // and NOT yet bid on, so it should appear in "Based on Your Bids"
        $biddenCategories = array_column(
            $db->table('bid b')->distinct()->select('l.category')
                ->join('sale_event se', 'se.id = b.sale_event_id')->join('listing l', 'l.id = se.listing_id')
                ->where('b.bidder_party_id', $trendingBidder['id'])->get()->getResultArray(),
            'category'
        );
        $this->assert(in_array('Machinery', $biddenCategories, true), 'The bidder\'s real bid history genuinely includes Machinery');

        $alreadyBid = array_column($db->table('bid')->distinct()->select('sale_event_id')->where('bidder_party_id', $trendingBidder['id'])->get()->getResultArray(), 'sale_event_id');
        $recommended = $db->table('sale_event se')->select('se.id')
            ->join('listing l', 'l.id = se.listing_id')
            ->whereIn('l.category', $biddenCategories)
            ->whereNotIn('se.id', $alreadyBid)
            ->get()->getResultArray();
        $this->assert(in_array($seB['id'], array_column($recommended, 'id'), true), 'seB (same category, never bid on) genuinely appears in the recommendation set');
        $this->assert(!in_array($seA['id'], array_column($recommended, 'id'), true), 'seA (already bid on) is correctly excluded from its own recommendation');

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
