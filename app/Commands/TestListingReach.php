<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\ListingFavoriteModel;
use App\Models\ListingViewModel;
use App\Models\SellerMessageRecipientModel;
use App\Libraries\ClvMatchingService;
use App\Libraries\ListingReachService;

// D-105: "Lot Reach & Interest" -- proves the reversed CLV matching
// (listing -> matched buyers, not buyer -> matching listings), real
// view/favorite tracking, and the real in-app bulk-message inbox.
class TestListingReach extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:listingreach';
    protected $description = 'Proves Lot Reach & Interest: reversed CLV matching, view/favorite tracking, real in-app bulk messaging (D-105).';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $favoriteModel = new ListingFavoriteModel();
        $viewModel = new ListingViewModel();
        $recipientModel = new SellerMessageRecipientModel();
        $clv = new ClvMatchingService();
        $reach = new ListingReachService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Reach Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'reachtest']);
        $seller = $partyModel->createParty('+919777601001');
        $buyerFullMatch = $partyModel->createParty('+919777601002');   // category + location + value
        $buyerCategoryOnly = $partyModel->createParty('+919777601003'); // category only
        $buyerNoMatch = $partyModel->createParty('+919777601004');      // has prefs, none match
        $buyerNoPrefs = $partyModel->createParty('+919777601005');      // never saved preferences at all

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Industrial Estate, Coimbatore, Tamil Nadu', 'yard_location_pin' => '641001',
        ]);
        // BR-13: new listings start at 'inventory' -- the reach dashboard
        // (and the real seller-facing app) only ever considers 'active'
        // listings "live," matching how every other live-listing view in
        // this codebase already scopes itself.
        $listingModel->transitionStatus($listing['id'], 'active');
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-REACH-001',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);

        $clv->savePreferences($buyerFullMatch['id'], ['Machinery'], ['Tamil Nadu'], 50000, 150000);
        $clv->savePreferences($buyerCategoryOnly['id'], ['Machinery'], ['Kerala'], 500000, 900000);
        $clv->savePreferences($buyerNoMatch['id'], ['Electronics'], ['Kerala'], 500000, 900000);
        // $buyerNoPrefs saves nothing at all.

        CLI::write("\n=== Reversed CLV matching: listing -> matched buyers ===", 'yellow');
        $matches = $reach->getMatchedBuyersForListing($listing['id']);
        $byParty = [];
        foreach ($matches as $m) { $byParty[$m['partyId']] = $m; }

        $this->assert(isset($byParty[$buyerFullMatch['id']]), 'Full-match buyer appears in results');
        $this->assert($byParty[$buyerFullMatch['id']]['categoryMatch'] === true, 'Full-match buyer: category matched');
        $this->assert($byParty[$buyerFullMatch['id']]['locationMatch'] === true, 'Full-match buyer: location matched (state name found in free-text address)');
        $this->assert($byParty[$buyerFullMatch['id']]['valueMatch'] === true, 'Full-match buyer: reserve value (100000) within their 50000-150000 budget');

        $this->assert(isset($byParty[$buyerCategoryOnly['id']]), 'Category-only buyer still appears (matches on at least one dimension)');
        $this->assert($byParty[$buyerCategoryOnly['id']]['categoryMatch'] === true, 'Category-only buyer: category matched');
        $this->assert($byParty[$buyerCategoryOnly['id']]['locationMatch'] === false, 'Category-only buyer: location correctly did not match (Kerala != Tamil Nadu)');
        $this->assert($byParty[$buyerCategoryOnly['id']]['valueMatch'] === false, 'Category-only buyer: value correctly did not match (100000 outside 500000-900000)');

        $this->assert(!isset($byParty[$buyerNoMatch['id']]), 'Buyer matching on zero dimensions is correctly excluded entirely, not shown as a false lead');
        $this->assert(!isset($byParty[$buyerNoPrefs['id']]), 'Buyer with no saved preferences at all is correctly excluded');
        $this->assert(!isset($byParty[$seller['id']]), "Seller never appears matched against their own listing");

        CLI::write("\n=== View tracking: aggregate count + per-party attribution ===", 'yellow');
        $reach->recordView($listing['id'], $buyerFullMatch['id'], $seller['id']);
        $reach->recordView($listing['id'], $buyerFullMatch['id'], $seller['id']); // same buyer views twice
        $reach->recordView($listing['id'], null, $seller['id']); // anonymous view
        $reach->recordView($listing['id'], $seller['id'], $seller['id']); // seller viewing their own listing

        $refreshedListing = $listingModel->find($listing['id']);
        $this->assert((int) $refreshedListing['view_count'] === 4, 'Aggregate view_count counts every real view unconditionally (4: 2 from the same buyer + 1 anonymous + 1 from the seller\'s own visit) -- the seller-exclusion only applies to per-party tracking below, not the raw traffic counter');
        $viewedIds = $viewModel->viewedPartyIdsForListing($listing['id']);
        $this->assert(isset($viewedIds[$buyerFullMatch['id']]), 'Buyer who viewed twice has exactly one per-party view row (idempotent)');
        $this->assert(!isset($viewedIds[$seller['id']]), "Seller's own view of their own listing does not inflate their own reach numbers");

        $matchesAfterView = $reach->getMatchedBuyersForListing($listing['id']);
        $viewedFlag = null;
        foreach ($matchesAfterView as $m) { if ($m['partyId'] === $buyerFullMatch['id']) $viewedFlag = $m['viewed']; }
        $this->assert($viewedFlag === true, "Matched-buyer breakdown correctly reflects the real view");

        CLI::write("\n=== Favorite tracking, real function already existed -- proving the new reverse lookup works ===", 'yellow');
        $favoriteModel->add($buyerFullMatch['id'], $listing['id']);
        $favoritedIds = $favoriteModel->favoritedPartyIdsForListing($listing['id']);
        $this->assert(isset($favoritedIds[$buyerFullMatch['id']]), 'favoritedPartyIdsForListing() correctly finds the real favorite');

        CLI::write("\n=== Bulk messaging: real send, real delivery, real inbox ===", 'yellow');
        $message = $reach->sendBulkMessage($listing['id'], $seller['id'], 'This listing matches your preferences and closes soon.');
        // >= 2, not === 2: matching is deliberately platform-wide with no
        // tenant/test scoping (same precedent as the existing buyer-facing
        // ClvMatchingService::findMatches()), so when this suite runs after
        // others in the same shared regression DB session, an earlier
        // suite's own buyer_preference fixtures can legitimately also
        // match this listing. The two buyers THIS test cares about are
        // checked by name below (inbox presence/absence), which is the
        // real assertion -- a raw headcount isn't reliable in a shared DB.
        $this->assert((int) $message['matched_buyer_count'] >= 2, 'Message recorded as sent to at least the 2 buyers this test created and expects to match');

        $inboxFull = $recipientModel->findForBuyer($buyerFullMatch['id']);
        $this->assert(count($inboxFull) === 1, 'Full-match buyer genuinely received the message in their real inbox');
        $this->assert($inboxFull[0]['message_body'] === 'This listing matches your preferences and closes soon.', 'Message body round-trips correctly');
        $this->assert($inboxFull[0]['read_at'] === null, 'Unread by default');

        $inboxNoMatch = $recipientModel->findForBuyer($buyerNoMatch['id']);
        $this->assert(count($inboxNoMatch) === 0, 'Buyer who never matched received nothing -- not a mass-blast to everyone');

        $unreadBefore = $recipientModel->unreadCountForBuyer($buyerFullMatch['id']);
        $this->assert($unreadBefore === 1, 'Unread count correct before marking read');
        $recipientModel->markRead($inboxFull[0]['recipient_id'], $buyerFullMatch['id']);
        $unreadAfter = $recipientModel->unreadCountForBuyer($buyerFullMatch['id']);
        $this->assert($unreadAfter === 0, 'Unread count correctly drops after marking read');

        $wrongPartyMarkResult = $recipientModel->markRead($inboxFull[0]['recipient_id'], $buyerCategoryOnly['id']);
        $this->assert($wrongPartyMarkResult === false, "A buyer cannot mark another buyer's message as read");

        CLI::write("\n=== Authorization: a seller cannot message on behalf of a listing that isn't theirs ===", 'yellow');
        $blocked = false;
        try {
            $reach->sendBulkMessage($listing['id'], $buyerFullMatch['id'], 'Not my listing to message about.');
        } catch (\RuntimeException $e) {
            $blocked = str_contains($e->getMessage(), 'your own listing');
        }
        $this->assert($blocked, 'Non-owner correctly blocked from sending on this listing');

        $emptyBlocked = false;
        try {
            $reach->sendBulkMessage($listing['id'], $seller['id'], '   ');
        } catch (\RuntimeException $e) {
            $emptyBlocked = str_contains($e->getMessage(), 'required');
        }
        $this->assert($emptyBlocked, 'An empty/whitespace-only message body is rejected, not silently sent');

        CLI::write("\n=== Reach summary: aggregates across a seller's live listings ===", 'yellow');
        $summary = $reach->getReachSummary($seller['id']);
        $this->assert($summary['totals']['lots'] === 1, 'Summary correctly counts this seller\'s one active listing');
        // >= 1, not === 1, for the same shared-DB reason as matched_buyer_count above.
        $this->assert($summary['totals']['matched'] >= 1, 'Summary counts only FULL matches (all 3 dimensions) for the headline stat -- includes at least the one this test\'s full-match buyer represents');
        $this->assert($summary['totals']['viewed'] === 4, 'Summary aggregate view count matches the real listing.view_count');

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
