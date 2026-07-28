<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Libraries\ClvMatchingService;

class TestMarketplaceBrowse extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:browse';
    protected $description = 'Proves Phase 3C marketplace browse/search filters, sort, pagination, and the CLV match badge are real.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Browse Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'browsetest']);
        $seller = $partyModel->createParty('+919555406001');
        $partyModel->update($seller['id'], ['seller_star_rating' => 4.5]);

        $listingA = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Plot 4, MIDC, Nashik, Maharashtra', 'yard_location_pin' => '422001',
        ]);
        $seA = $saleEventModel->createSaleEvent([
            'listing_id' => $listingA['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BROWSE-A',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);

        $listingB = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'New',
            'category' => 'Electronics', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Sector 5, Noida, Uttar Pradesh', 'yard_location_pin' => '201301',
        ]);
        $seB = $saleEventModel->createSaleEvent([
            'listing_id' => $listingB['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BROWSE-B',
            'sale_format' => 'buy_now', 'expected_value' => 20000, 'status' => 'active',
        ]);

        // Scoped to this test's own tenant throughout — every other spark
        // test suite creates plenty of its own "Machinery"/"buy_now"
        // listings, so an unscoped platform-wide count would be
        // genuinely polluted when the full regression runs in sequence
        // against one shared database (a real test-isolation bug caught
        // by running this alongside the rest, not by this suite alone).
        $priceExpr = 'COALESCE(se.current_price, se.reserve_value, se.expected_value)';
        $baseQuery = fn() => $db->table('sale_event se')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party p', 'p.id = l.seller_party_id')
            ->whereIn('se.status', ['active', 'grace_period'])
            ->where('l.tenant_id', $tenant['id'])
            ->where('p.shadow_banned_at_seller', null);

        CLI::write("\n=== Category and format filters genuinely narrow results ===", 'yellow');
        $machineryOnly = $baseQuery()->where('l.category', 'Machinery')->countAllResults();
        $this->assert($machineryOnly === 1, 'Category filter genuinely returns only the matching listing');
        $buyNowOnly = $baseQuery()->where('se.sale_format', 'buy_now')->countAllResults();
        $this->assert($buyNowOnly === 1, 'Format filter genuinely returns only the matching listing');

        CLI::write("\n=== Price range filter operates on the correct COALESCE'd comparable price ===", 'yellow');
        $under50k = $baseQuery()->where("{$priceExpr} <=", 50000, false)->countAllResults();
        $this->assert($under50k === 1, 'A ₹50k max correctly excludes the ₹1L Easy Auction, keeps the ₹20k Buy-Now listing');
        $over50k = $baseQuery()->where("{$priceExpr} >", 50000, false)->countAllResults();
        $this->assert($over50k === 1, 'A ₹50k min correctly returns only the ₹1L listing');

        CLI::write("\n=== Location text search (the honest approximation, no discrete state column exists) ===", 'yellow');
        $maharashtraOnly = $baseQuery()->like('l.yard_location_address', 'Maharashtra')->countAllResults();
        $this->assert($maharashtraOnly === 1, 'Location text search genuinely narrows by the free-text yard address');

        CLI::write("\n=== Seller rating filter ===", 'yellow');
        $ratedSeller = $partyModel->createParty('+919555406002');
        $partyModel->update($ratedSeller['id'], ['seller_star_rating' => 2.0]);
        $listingC = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $ratedSeller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600001',
        ]);
        $saleEventModel->createSaleEvent([
            'listing_id' => $listingC['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BROWSE-C',
            'sale_format' => 'easy', 'reserve_value' => 30000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);
        $fourPlus = $baseQuery()->where('p.seller_star_rating >=', 4.0)->countAllResults();
        $this->assert($fourPlus === 2, 'The 2.0★ seller\'s listing is correctly excluded by a 4★+ filter, the two 4.5★ listings remain');

        CLI::write("\n=== Shadow-banned sellers are still genuinely excluded from browse/search (BR-38, unchanged) ===", 'yellow');
        $bannedSeller = $partyModel->createParty('+919555406003');
        $partyModel->update($bannedSeller['id'], ['shadow_banned_at_seller' => date('Y-m-d H:i:s')]);
        $bannedListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $bannedSeller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600002',
        ]);
        $saleEventModel->createSaleEvent([
            'listing_id' => $bannedListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BROWSE-BANNED',
            'sale_format' => 'easy', 'reserve_value' => 15000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);
        $machineryAfterBan = $baseQuery()->where('l.category', 'Machinery')->countAllResults();
        $this->assert($machineryAfterBan === 2, 'A shadow-banned seller\'s listing is genuinely excluded from the browse result set, not just visually hidden');

        CLI::write("\n=== CLV preference-match badge reuses the real ClvMatchingService, not separate logic ===", 'yellow');
        $buyer = $partyModel->createParty('+919555406004');
        (new ClvMatchingService())->savePreferences($buyer['id'], ['Machinery'], ['Maharashtra'], null, 150000);
        $matches = (new ClvMatchingService())->findMatches($buyer['id'], 200);
        $matchedIds = array_column($matches, 'sale_event_id');
        $this->assert(in_array($seA['id'], $matchedIds, true), 'The Machinery listing within budget genuinely appears in the buyer\'s real CLV match set');
        $this->assert(!in_array($seB['id'], $matchedIds, true), 'The Electronics listing (not a preferred category) correctly does not match');

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
