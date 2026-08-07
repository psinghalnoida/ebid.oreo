<?php

namespace App\Libraries;

use App\Models\ListingModel;
use App\Models\BuyerPreferenceModel;
use App\Models\ListingFavoriteModel;
use App\Models\ListingViewModel;
use App\Models\SellerMessageModel;
use App\Models\SellerMessageRecipientModel;

// D-105: "Lot Reach & Interest" -- reverses ClvMatchingService's
// existing buyer-facing direction (buyer -> matching listings) into a
// seller-facing one (this listing -> which buyers match it), and adds
// real per-listing view/favorite visibility plus a real in-app bulk
// message a seller can actually send to those matched buyers.
//
// Location matching is a deliberate, documented compromise, not an
// oversight: the listing table has no normalized state field (only
// free-text yard_location_address + a PIN code -- see D-11/listing
// schema), and buyer_preference.comfort_states is itself free text
// ("Tamil Nadu, Karnataka"). Matched here via a case-insensitive
// substring check against the address, the same level of rigor the
// rest of this free-text location data already has elsewhere in the
// app -- not a new precision gap introduced by this feature.
class ListingReachService
{
    private ListingModel $listingModel;
    private BuyerPreferenceModel $preferenceModel;
    private ListingFavoriteModel $favoriteModel;
    private ListingViewModel $viewModel;
    private SellerMessageModel $messageModel;
    private SellerMessageRecipientModel $recipientModel;

    public function __construct()
    {
        $this->listingModel = new ListingModel();
        $this->preferenceModel = new BuyerPreferenceModel();
        $this->favoriteModel = new ListingFavoriteModel();
        $this->viewModel = new ListingViewModel();
        $this->messageModel = new SellerMessageModel();
        $this->recipientModel = new SellerMessageRecipientModel();
    }

    // Called from ListingController::show() on every real page view --
    // unconditional aggregate count, plus a per-party row when the
    // viewer is logged in (and isn't the listing's own seller, whose
    // own visits to their own listing shouldn't inflate their own reach
    // numbers).
    public function recordView(string $listingId, ?string $viewerPartyId, string $sellerPartyId): void
    {
        $this->listingModel->builder()->where('id', $listingId)->set('view_count', 'view_count + 1', false)->update();
        if ($viewerPartyId && $viewerPartyId !== $sellerPartyId) {
            $this->viewModel->recordView($listingId, $viewerPartyId);
        }
    }

    // Summary stats across every live listing this seller owns --
    // "Live Lots" / "Full Matches (CAT+LOC+VAL)" / "Total Views".
    public function getReachSummary(string $sellerPartyId): array
    {
        $listings = $this->listingModel->where('seller_party_id', $sellerPartyId)
            ->where('status', 'active')->findAll();

        $totalMatched = 0;
        $totalViews = 0;
        $perListing = [];
        foreach ($listings as $listing) {
            $matches = $this->getMatchedBuyersForListing($listing['id']);
            $fullMatches = count(array_filter($matches, fn($m) => $m['categoryMatch'] && $m['locationMatch'] && $m['valueMatch']));
            $totalMatched += $fullMatches;
            $totalViews += (int) $listing['view_count'];
            $perListing[] = [
                'listing' => $listing,
                'matchedBuyers' => $matches,
                'fullMatchCount' => $fullMatches,
            ];
        }

        return [
            'totals' => ['lots' => count($listings), 'matched' => $totalMatched, 'viewed' => $totalViews],
            'listings' => $perListing,
        ];
    }

    // The core reversal: given a listing, which buyers with SAVED
    // preferences match it, and on which of the three dimensions.
    // Mirrors ClvMatchingService::findMatches()'s own category/budget
    // logic exactly (same semantics, opposite direction) and adds the
    // location check that method itself never actually applied despite
    // buyers being able to save it (a pre-existing gap in that method,
    // not introduced here -- see BuyerPreferenceModel::comfort_states).
    public function getMatchedBuyersForListing(string $listingId): array
    {
        $listing = $this->listingModel->find($listingId);
        if (!$listing) {
            return [];
        }

        $db = \Config\Database::connect();
        $saleEvent = $db->table('sale_event')->where('listing_id', $listingId)
            ->orderBy('created_at', 'DESC')->get()->getRowArray();
        $price = $saleEvent ? (float) ($saleEvent['current_price'] ?? $saleEvent['reserve_value'] ?? $saleEvent['expected_value'] ?? 0) : null;

        $prefs = $db->table('buyer_preference bp')
            ->select('bp.*, p.id as party_id')
            ->join('party p', 'p.id = bp.party_id')
            ->where('p.id !=', $listing['seller_party_id'])
            ->where('p.shadow_banned_at_buyer', null)
            ->get()->getResultArray();

        $favorited = $this->favoriteModel->favoritedPartyIdsForListing($listingId);
        $viewed = $this->viewModel->viewedPartyIdsForListing($listingId);

        $results = [];
        foreach ($prefs as $pref) {
            $categoryMatch = false;
            if (!empty($pref['preferred_categories'])) {
                $categories = json_decode($pref['preferred_categories'], true) ?: [];
                $categoryMatch = in_array($listing['category'], $categories, true);
            }

            $locationMatch = false;
            if (!empty($pref['comfort_states'])) {
                $states = json_decode($pref['comfort_states'], true) ?: [];
                foreach ($states as $state) {
                    if ($state !== '' && stripos((string) $listing['yard_location_address'], $state) !== false) {
                        $locationMatch = true;
                        break;
                    }
                }
            }

            $valueMatch = false;
            if ($price !== null) {
                $min = $pref['budget_min'] !== null ? (float) $pref['budget_min'] : null;
                $max = $pref['budget_max'] !== null ? (float) $pref['budget_max'] : null;
                $valueMatch = ($min === null || $price >= $min) && ($max === null || $price <= $max);
            }

            // A buyer shows up here at all only if they match on at
            // least one dimension -- someone matching on nothing isn't
            // a "reach" candidate, just noise.
            if (!$categoryMatch && !$locationMatch && !$valueMatch) {
                continue;
            }

            $results[] = [
                'partyId' => $pref['party_id'],
                'categoryMatch' => $categoryMatch,
                'locationMatch' => $locationMatch,
                'valueMatch' => $valueMatch,
                'favorited' => isset($favorited[$pref['party_id']]),
                'viewed' => isset($viewed[$pref['party_id']]),
            ];
        }

        return $results;
    }

    // BR-independent (this is a net-new feature, not a spec item) --
    // sends one message to every buyer CURRENTLY matched against this
    // listing. Re-checks ownership so a seller can't message on behalf
    // of a listing that isn't theirs.
    public function sendBulkMessage(string $listingId, string $sellerPartyId, string $messageBody): array
    {
        $listing = $this->listingModel->find($listingId);
        if (!$listing || $listing['seller_party_id'] !== $sellerPartyId) {
            throw new \RuntimeException('You may only message buyers matched against your own listing.');
        }
        $trimmed = trim($messageBody);
        if ($trimmed === '') {
            throw new \RuntimeException('A message body is required.');
        }

        $matches = $this->getMatchedBuyersForListing($listingId);
        $buyerIds = array_column($matches, 'partyId');

        $message = $this->messageModel->createMessage($listingId, $sellerPartyId, $trimmed, count($buyerIds));
        if (!empty($buyerIds)) {
            $this->recipientModel->deliverTo($message['id'], $buyerIds);
        }

        (new AuditLogService())->log('listing.reach_message_sent', $sellerPartyId, [
            'listingId' => $listingId, 'messageId' => $message['id'], 'matchedBuyerCount' => count($buyerIds),
        ]);

        return $message;
    }
}
