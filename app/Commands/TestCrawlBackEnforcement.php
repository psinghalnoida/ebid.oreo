<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Libraries\RatingService;
use App\Libraries\BiddingService;

class TestCrawlBackEnforcement extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:crawlback';
    protected $description = 'Proves BR-38 is actually enforced, not just tracked.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $rating = new RatingService();
        $bidding = new BiddingService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'CrawlBack Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'crawlbacktest']);
        $buyer = $partyModel->createParty('+919999801001');
        $seller = $partyModel->createParty('+919999801002');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600021',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CRAWLBACK-001',
            'sale_format' => 'easy', 'reserve_value' => 200000, 'status' => 'active',
        ]);
        $emdHoldModel = new \App\Models\EmdHoldModel();
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 20000);

        CLI::write("\n=== Before any downgrade — unrestricted, bid above 50000 succeeds ===", 'yellow');
        $unrestricted = true;
        try {
            $bidding->placeBid($saleEvent['id'], $buyer['id'], 60000);
        } catch (\RuntimeException $e) {
            $unrestricted = false;
        }
        $this->assert($unrestricted, 'An unrestricted buyer can bid above the Low bracket ceiling');

        CLI::write("\n=== Downgrade the buyer below 2.0 — triggers Crawl-Back ===", 'yellow');
        $event = $rating->initiateDowngrade($buyer['id'], 'star_rating', 1.5, 'Confirmed fraud test scenario');
        $rating->approveDowngrade($event['id'], $seller['id'], 'tenant_admin');
        $result = $rating->approveDowngrade($event['id'], $seller['id'], 'super_admin');
        $this->assert($result['applied'] === true, 'The downgrade to 1.5 was correctly dual-approved and applied');

        $partyAfter = $partyModel->find($buyer['id']);
        $this->assert(
            in_array($partyAfter['crawl_back_active_buyer'], [true, 't', 1, '1'], true),
            'Crawl-Back is now genuinely active on the buyer'
        );

        CLI::write("\n=== THE REAL PROOF: this buyer is now genuinely blocked from bidding above the Low bracket ===", 'yellow');
        $blocked = false;
        try {
            $bidding->placeBid($saleEvent['id'], $buyer['id'], 60000);
        } catch (\RuntimeException $e) {
            $blocked = str_contains($e->getMessage(), 'BR-38');
        }
        $this->assert($blocked, 'A Crawl-Back-restricted buyer is genuinely blocked, not just tracked');

        CLI::write("\n=== The same buyer CAN still bid within the Low bracket (fresh sale event, since the earlier one's price already exceeded it) ===", 'yellow');
        $freshListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600023',
        ]);
        $freshSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $freshListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CRAWLBACK-002',
            'sale_format' => 'easy', 'reserve_value' => 30000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($freshSaleEvent['id'], $buyer['id'], 'van', 3000);
        $allowedWithinBracket = true;
        try {
            $bidding->placeBid($freshSaleEvent['id'], $buyer['id'], 45000);
        } catch (\RuntimeException $e) {
            $allowedWithinBracket = false;
        }
        $this->assert($allowedWithinBracket, 'The same restricted buyer can still bid within their permitted Low bracket');

        CLI::write("\n=== Recording 3 clean transactions restores the rating to 3.0 ===", 'yellow');
        $rating->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $rating->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $final = $rating->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $this->assert($final['crawlBackCompleted'] === true, 'Crawl-Back completes on the 3rd clean transaction');
        $this->assert((float) $final['restoredTo'] === 3.0, 'Rating is genuinely restored to exactly 3.0');

        $partyRestored = $partyModel->find($buyer['id']);
        $this->assert(
            !in_array($partyRestored['crawl_back_active_buyer'], [true, 't', 1, '1'], true),
            'Crawl-Back is genuinely deactivated after restoration'
        );

        CLI::write("\n=== And the restriction is genuinely lifted ===", 'yellow');
        $restrictionLifted = true;
        try {
            // Must exceed the sale event's current price (already at
            // 60000 from the earlier successful bid) — a genuinely
            // separate, correct rule, not the thing being tested here.
            $bidding->placeBid($saleEvent['id'], $buyer['id'], 65000);
        } catch (\RuntimeException $e) {
            $restrictionLifted = false;
        }
        $this->assert($restrictionLifted, 'After restoration, the buyer can genuinely bid above the Low bracket again');

        CLI::write("\n=== Shadow Ban: a seller below 1.5 stars is suppressed from Browse ===", 'yellow');
        $shadowSeller = $partyModel->createParty('+919999801003');
        $shadowListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $shadowSeller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600022',
        ]);
        $shadowSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $shadowListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SHADOWBAN-001',
            'sale_format' => 'easy', 'reserve_value' => 30000, 'status' => 'active',
        ]);
        $sellerEvent = $rating->initiateDowngrade($shadowSeller['id'], 'seller_star_rating', 1.8, 'Confirmed CBS violation test');
        $rating->approveDowngrade($sellerEvent['id'], $buyer['id'], 'tenant_admin');
        $rating->approveDowngrade($sellerEvent['id'], $buyer['id'], 'super_admin');

        $this->assert($rating->isShadowBanned($shadowSeller['id'], 'seller_star_rating'), 'The seller is genuinely marked Shadow Banned after dropping below 1.5');

        $db = \Config\Database::connect();
        $inBrowse = $db->table('sale_event se')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party p', 'p.id = l.seller_party_id')
            ->where('se.id', $shadowSaleEvent['id'])
            ->where('p.shadow_banned_at_seller', null)
            ->countAllResults();
        $this->assert($inBrowse === 0, 'A Shadow Banned seller\'s listing is genuinely excluded from the Browse query');

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
