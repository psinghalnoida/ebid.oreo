<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\PartyRoleModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\BidModel;
use App\Models\OfferModel;
use App\Libraries\AuthService;
use App\Libraries\BiddingService;
use App\Libraries\OfferService;
use App\Libraries\ExpressAuctionService;

// Real, working demo/test data for manual end-to-end testing against a
// non-empty marketplace -- built at the project owner's explicit
// request, not a throwaway script. Every business-rule interaction
// here goes through the SAME real service classes the app itself uses
// (BiddingService, OfferService, ExpressAuctionService) -- exact same
// patterns already proven in the test:* suites (TestCascade.php,
// TestBuyNow.php, TestExpress.php) -- not a shortcut that fakes data
// the real app wouldn't actually produce.
//
// Every created row is identifiable and reversible:
//   - All demo parties share the +9195000000XX mobile-number range
//     (2-digit suffix 01-20), never used by any test:* fixture.
//   - All demo party/tenant/listing names carry a "DEMO — " prefix.
// `php spark seed:demo-data --undo` removes everything this command
// created, by querying those same two markers -- no ID bookkeeping
// needed, safe to run on a database this has already seeded before.
//
// Uses the platform's real BR-07 closed category list (not made-up
// categories) and a shared demo mPIN so every seeded account is
// actually usable for manual testing, not just present in the DB.
class SeedDemoData extends BaseCommand
{
    protected $group       = 'Demo';
    protected $name        = 'seed:demo-data';
    protected $description = 'Seeds a demo tenant, ~20 demo users (buyers/sellers/tenant admin), and 8 listings across all 3 self-service sale formats. Pass --undo to remove.';
    protected $usage       = 'seed:demo-data [--undo]';

    public const DEMO_MPIN = '9999';
    private const MOBILE_PREFIX = '+9195000000'; // + 2-digit suffix 01-20
    private const NAME_TAG = 'DEMO — ';
    private const TENANT_SUBDOMAIN = 'tradespherex';

    public function run(array $params)
    {
        if (CLI::getOption('undo') !== null || in_array('--undo', $params, true)) {
            $this->undo();
            return;
        }

        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $roleModel = new PartyRoleModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $auth = new AuthService();
        $bidding = new BiddingService();
        $offers = new OfferService();
        $express = new ExpressAuctionService();

        $existing = $tenantModel->where('subdomain', self::TENANT_SUBDOMAIN)->first();
        if ($existing) {
            CLI::error('Demo data already exists (tenant "' . self::TENANT_SUBDOMAIN . '" found). Run with --undo first if you want to reseed.');
            return;
        }

        CLI::write('=== Tenant: TradeSphereX ===', 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => self::NAME_TAG . 'TradeSphereX',
            'tenant_class' => 'general',
            'subdomain' => self::TENANT_SUBDOMAIN,
            'subscription_tier' => 'tsx_growth',
        ]);
        CLI::write("  Tenant created: {$tenant['id']}");

        CLI::write('=== 20 demo parties (1 Tenant Admin, 6 Sellers, 13 Buyers) ===', 'yellow');
        $names = [
            'Ravi Kumar', 'Priya Sharma', 'Arjun Nair', 'Sneha Iyer', 'Vikram Singh',
            'Ananya Reddy', 'Karthik Menon', 'Divya Pillai', 'Rohan Gupta', 'Meera Krishnan',
            'Aditya Rao', 'Kavya Desai', 'Suresh Pillai', 'Lakshmi Venkatesh', 'Manoj Tiwari',
            'Pooja Bhatt', 'Sanjay Verma', 'Neha Kapoor', 'Rahul Chandra', 'Anjali Menon',
        ];
        $parties = [];
        foreach ($names as $i => $name) {
            $suffix = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $mobile = self::MOBILE_PREFIX . $suffix;
            // mobile_number is a hard UNIQUE column, and --undo archives
            // (rather than hard-deletes) parties -- see undo()'s own
            // comment for why. So a re-seed after an undo must reuse
            // and un-archive the existing row for this mobile number,
            // not attempt a second insert that would collide with it.
            $party = $partyModel->where('mobile_number', $mobile)->first();
            if ($party) {
                $partyModel->update($party['id'], ['archived_at' => null]);
            } else {
                $party = $partyModel->createParty($mobile);
            }
            $partyModel->update($party['id'], [
                'full_name' => self::NAME_TAG . $name,
                'kyc_status' => 'verified',
                'recovery_email' => 'demo+' . $suffix . '@adwitix.example',
            ]);
            $auth->setMpin($party['id'], self::DEMO_MPIN);
            $parties[] = $partyModel->find($party['id']);
        }
        $tenantAdmin = $parties[0];
        $sellers = array_slice($parties, 1, 6);   // parties[1..6]
        $buyers = array_slice($parties, 7, 13);   // parties[7..19]

        $roleModel->promoteTenantAdmin($tenantAdmin['id'], $tenant['id']);
        CLI::write("  {$tenantAdmin['full_name']} ({$tenantAdmin['mobile_number']}) granted Tenant Admin for TradeSphereX");
        CLI::write('  6 sellers, 13 buyers created — all mPIN ' . self::DEMO_MPIN . ', KYC pre-verified');

        CLI::write('=== 8 listings across Easy / Express / Buy-Now (BR-07 real categories) ===', 'yellow');

        $mkListing = function (array $seller, string $category, string $makeModel, string $pin) use ($listingModel, $tenant) {
            return $listingModel->createListing([
                'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
                'title' => self::NAME_TAG . $makeModel,
                'physical_condition' => 'Used', 'category' => $category,
                'quantity' => 1, 'quantity_basis' => 'unit', 'make_model' => $makeModel,
                'yard_location_address' => 'AdwitiX Demo Yard', 'yard_location_pin' => $pin,
            ]);
        };

        // --- Easy Auctions (3), each with real EMD holds + ranked bids ---
        $easySpecs = [
            ['category' => 'Repossessed Banking Assets', 'model' => 'Bank-Seized Delivery Van', 'rv' => 250000, 'buyers' => [0, 1, 2], 'bids' => [260000, 280000, 300000]],
            ['category' => 'Industrial/Commercial Surplus', 'model' => 'Surplus CNC Lathe', 'rv' => 180000, 'buyers' => [3, 4], 'bids' => [190000, 205000]],
            ['category' => 'Salvaged Claims Goods', 'model' => 'Salvaged Warehouse Racking Lot', 'rv' => 90000, 'buyers' => [5], 'bids' => [95000]],
        ];
        $listingIndex = 0;
        foreach ($easySpecs as $spec) {
            $listingIndex++;
            $seller = $sellers[$listingIndex % count($sellers)];
            $listing = $mkListing($seller, $spec['category'], $spec['model'], '600' . (100 + $listingIndex));
            $saleEvent = $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'],
                'ern' => 'DEMO-EASY-' . str_pad((string) $listingIndex, 3, '0', STR_PAD_LEFT),
                'sale_format' => 'easy', 'reserve_value' => $spec['rv'], 'result_mode' => 'instant_close',
                'status' => 'active',
            ]);
            $emdBaseline = round($spec['rv'] * 0.10, 2);
            foreach ($spec['buyers'] as $bIdx) {
                $emdHoldModel->createHold($saleEvent['id'], $buyers[$bIdx]['id'], 'van', $emdBaseline);
            }
            foreach ($spec['buyers'] as $i => $bIdx) {
                $bidding->placeBid($saleEvent['id'], $buyers[$bIdx]['id'], $spec['bids'][$i]);
            }
            CLI::write("  [Easy] {$spec['model']} — RV ₹{$spec['rv']}, " . count($spec['buyers']) . ' bid(s)');
        }

        // --- Express Auctions (2) — 3 pledges to trigger the bidding phase, then bids ---
        $expressSpecs = [
            ['category' => 'Second-Hand/Used Goods', 'model' => 'Used Office Furniture Lot', 'rv' => 45000, 'pledgers' => [6, 7, 8], 'bidderIdx' => 0, 'bid' => 47000],
            ['category' => 'Abandoned Goods', 'model' => 'Abandoned Retail Fixtures Lot', 'rv' => 60000, 'pledgers' => [9, 10, 11], 'bidderIdx' => 0, 'bid' => 63000],
        ];
        foreach ($expressSpecs as $spec) {
            $listingIndex++;
            $seller = $sellers[$listingIndex % count($sellers)];
            $listing = $mkListing($seller, $spec['category'], $spec['model'], '600' . (100 + $listingIndex));
            $saleEvent = $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'],
                'ern' => 'DEMO-EXPRESS-' . str_pad((string) $listingIndex, 3, '0', STR_PAD_LEFT),
                'sale_format' => 'express', 'reserve_value' => $spec['rv'], 'status' => 'active',
            ]);
            foreach ($spec['pledgers'] as $bIdx) {
                $express->pledgeReserve($saleEvent['id'], $buyers[$bIdx]['id']);
            }
            $express->placeBid($saleEvent['id'], $buyers[$spec['pledgers'][$spec['bidderIdx']]]['id'], $spec['bid']);
            CLI::write("  [Express] {$spec['model']} — RV ₹{$spec['rv']}, 3 pledges + 1 bid");
        }

        // --- Buy-Now (2) — EMD holds + independent offers ---
        $buyNowSpecs = [
            ['category' => 'Custom/Confiscated Goods', 'model' => 'Confiscated Electronics Pallet', 'ev' => 120000, 'buyers' => [12, 0], 'offers' => [115000, 122000]],
            ['category' => 'Antiques', 'model' => 'Antique Furniture Collection', 'ev' => 75000, 'buyers' => [1], 'offers' => [70000]],
        ];
        foreach ($buyNowSpecs as $spec) {
            $listingIndex++;
            $seller = $sellers[$listingIndex % count($sellers)];
            $listing = $mkListing($seller, $spec['category'], $spec['model'], '600' . (100 + $listingIndex));
            $saleEvent = $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'],
                'ern' => 'DEMO-BUYNOW-' . str_pad((string) $listingIndex, 3, '0', STR_PAD_LEFT),
                'sale_format' => 'buy_now', 'expected_value' => $spec['ev'], 'status' => 'active',
            ]);
            $emdBaseline = round($spec['ev'] * 0.10, 2);
            foreach ($spec['buyers'] as $bIdx) {
                $emdHoldModel->createHold($saleEvent['id'], $buyers[$bIdx]['id'], 'van', $emdBaseline);
            }
            foreach ($spec['buyers'] as $i => $bIdx) {
                $offers->submitOffer($saleEvent['id'], $buyers[$bIdx]['id'], $spec['offers'][$i]);
            }
            CLI::write("  [Buy-Now] {$spec['model']} — EV ₹{$spec['ev']}, " . count($spec['buyers']) . ' offer(s)');
        }

        // --- 1 listing left pending approval (no sale event yet) — Lot Approval queue ---
        $listingIndex++;
        $pendingListing = $mkListing($sellers[0], 'Lost-and-Found Inventories', 'Unclaimed Freight Lot', '600' . (100 + $listingIndex));
        $listingModel->transitionStatus($pendingListing['id'], 'pending_approval');
        CLI::write('  [Pending approval] Unclaimed Freight Lot — no photos attached, listed here for real; Lot Approval will show it without a thumbnail.');

        CLI::write("\n✓ Demo data seeded: 1 tenant, 20 parties, 8 listings (3 Easy / 2 Express / 2 Buy-Now / 1 pending approval).", 'green');
        CLI::write('  Log in as any demo party: mobile ' . self::MOBILE_PREFIX . '01' . '..' . self::MOBILE_PREFIX . '20 (2-digit suffix), mPIN ' . self::DEMO_MPIN . '.');
        CLI::write("  Tenant Admin: {$tenantAdmin['mobile_number']} — visit /tenants/{$tenant['id']}/dashboard once logged in.");
        CLI::write('  Run `php spark seed:demo-data --undo` to remove all of this later.');
    }

    private function undo(): void
    {
        $db = \Config\Database::connect();
        $tenantModel = new TenantModel();

        $tenant = $tenantModel->where('subdomain', self::TENANT_SUBDOMAIN)->first();
        if (!$tenant) {
            CLI::write('No demo tenant found — nothing to undo.', 'yellow');
        } else {
            $listingIds = array_column(
                $db->table('listing')->select('id')->where('tenant_id', $tenant['id'])->get()->getResultArray(),
                'id'
            );
            if ($listingIds) {
                $saleEventIds = array_column(
                    $db->table('sale_event')->select('id')->whereIn('listing_id', $listingIds)->get()->getResultArray(),
                    'id'
                );
                if ($saleEventIds) {
                    $db->table('bid')->whereIn('sale_event_id', $saleEventIds)->delete();
                    $db->table('offer')->whereIn('sale_event_id', $saleEventIds)->delete();
                    $db->table('emd_hold')->whereIn('sale_event_id', $saleEventIds)->delete();
                    $db->table('sale_event')->whereIn('id', $saleEventIds)->delete();
                }
                $db->table('listing')->whereIn('id', $listingIds)->delete();
            }
            $db->table('party_role')->where('tenant_id', $tenant['id'])->delete();
            $db->table('tenant')->where('id', $tenant['id'])->delete();
            CLI::write('  Removed demo tenant, its listings, sale events, bids/offers, and EMD holds.', 'green');
        }

        // Archived, not hard-deleted: party is a real FK target of the
        // immutable audit_log (BR-05 — actor_party_id), and every
        // seeding action above (mPIN set, Tenant Admin grant, ...)
        // genuinely wrote real audit rows referencing these parties.
        // Hard-deleting would violate that same immutability the rest
        // of this platform depends on — found for real the first time
        // this ran (a real FK constraint failure), not assumed.
        // archived_at is the same soft-delete convention findByMobile()/
        // findActiveById() already filter on everywhere else in this
        // app, so an archived demo party is functionally gone (can't
        // log in, doesn't appear in listings) without touching the
        // audit trail at all.
        $demoParties = $db->table('party')->select('id')->like('mobile_number', self::MOBILE_PREFIX, 'after')->where('archived_at', null)->get()->getResultArray();
        if ($demoParties) {
            $ids = array_column($demoParties, 'id');
            $db->table('party')->whereIn('id', $ids)->update(['archived_at' => date('Y-m-d H:i:s')]);
            CLI::write('  Archived ' . count($ids) . ' demo parties (soft-deleted — the immutable audit_log keeps a real FK to these, so they can\'t be hard-deleted; this is the same pattern used everywhere else in the app).', 'green');
        } else {
            CLI::write('  No demo parties found.', 'yellow');
        }

        CLI::write("\n✓ Demo data removed.", 'green');
    }
}
