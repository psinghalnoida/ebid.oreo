<?php

namespace App\Controllers;

use App\Models\ListingFavoriteModel;
use App\Models\SavedSearchModel;
use App\Models\SearchHistoryModel;

class DiscoveryController extends BaseController
{
    private function requireLogin(): ?string
    {
        return session()->get('logged_in_party_id');
    }

    public function myFavorites()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        return view('my/favorites', ['title' => 'My Favorites — eBid Hub', 'favorites' => (new ListingFavoriteModel())->findForParty($partyId)]);
    }

    public function mySearches()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        return view('my/searches', ['title' => 'Saved Searches — eBid Hub', 'searches' => (new SavedSearchModel())->findForParty($partyId)]);
    }

    // Saves whatever filter combination is in the querystring right now
    // — meant to be POSTed to from the /listings page's own filter form
    // via a "Save this search" button carrying the current query along.
    public function saveSearchSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $label = $this->request->getPost('label') ?: 'Untitled search';
        $filters = [];
        foreach (['category', 'format', 'price_min', 'price_max', 'location', 'min_rating', 'condition', 'posted', 'sort', 'q'] as $key) {
            $value = $this->request->getPost($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        (new SavedSearchModel())->create($partyId, $label, $filters);
        return redirect()->to('/my-searches');
    }

    public function deleteSearch(string $searchId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        (new SavedSearchModel())->where('id', $searchId)->where('party_id', $partyId)->delete();
        return redirect()->to('/my-searches');
    }

    public function searchHistory()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $history = (new SearchHistoryModel())->findRecentForParty($partyId, 20);
        foreach ($history as &$h) {
            $h['filtersDecoded'] = json_decode($h['filters'], true) ?: [];
        }
        unset($h);

        return view('my/search_history', ['title' => 'Search History — eBid Hub', 'history' => $history]);
    }

    // "Trending now" (real bid-count activity in the last 24h) and
    // "Based on your bids" (other active listings in categories the
    // buyer has actually bid in before, excluding ones they've already
    // bid on) — both real queries against existing data, no separate
    // recommendation engine.
    public function recommendations()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $db = \Config\Database::connect();

        $trending = $db->table('bid b')
            ->select('se.id as sale_event_id, se.sale_format, l.id as listing_id, l.category,
                      COALESCE(se.current_price, se.reserve_value, se.expected_value) as display_price,
                      COUNT(b.id) as bid_count')
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party p', 'p.id = l.seller_party_id')
            ->where('b.placed_at >=', date('Y-m-d H:i:s', strtotime('-1 day')))
            ->whereIn('se.status', ['active', 'grace_period'])
            ->where('p.shadow_banned_at_seller', null)
            ->groupBy('se.id, se.sale_format, l.id, l.category, se.current_price, se.reserve_value, se.expected_value')
            ->orderBy('bid_count', 'DESC')
            ->limit(12)->get()->getResultArray();

        $biddenCategories = $db->table('bid b')
            ->distinct()->select('l.category')
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('b.bidder_party_id', $partyId)
            ->get()->getResultArray();
        $categories = array_column($biddenCategories, 'category');

        $basedOnBids = [];
        if (!empty($categories)) {
            $alreadyBidSaleEventIds = array_column(
                $db->table('bid')->distinct()->select('sale_event_id')->where('bidder_party_id', $partyId)->get()->getResultArray(),
                'sale_event_id'
            );

            $query = $db->table('sale_event se')
                ->select('se.id as sale_event_id, se.sale_format, l.id as listing_id, l.category,
                          COALESCE(se.current_price, se.reserve_value, se.expected_value) as display_price')
                ->join('listing l', 'l.id = se.listing_id')
                ->join('party p', 'p.id = l.seller_party_id')
                ->whereIn('se.status', ['active', 'grace_period'])
                ->whereIn('l.category', $categories)
                ->where('p.shadow_banned_at_seller', null);
            if (!empty($alreadyBidSaleEventIds)) {
                $query->whereNotIn('se.id', $alreadyBidSaleEventIds);
            }
            $basedOnBids = $query->orderBy('l.created_at', 'DESC')->limit(12)->get()->getResultArray();
        }

        return view('my/recommendations', ['title' => 'Recommendations — eBid Hub', 'trending' => $trending, 'basedOnBids' => $basedOnBids]);
    }
}
