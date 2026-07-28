<?php

namespace App\Controllers;

use App\Libraries\Paginator;

class Home extends BaseController
{
    public function index(): string
    {
        $db = \Config\Database::connect();

        $activeListings = $db->table('sale_event se')
            ->select('se.id as sale_event_id, se.ern, se.sale_format, se.status, se.current_price, se.reserve_value, se.expected_value,
                      l.id as listing_id, l.category, l.subcategory, l.physical_condition, l.yard_location_pin,
                      lm.file_path as photo_path')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party p', 'p.id = l.seller_party_id')
            ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false)
            ->whereIn('se.status', ['active', 'grace_period'])
            ->where('p.shadow_banned_at_seller', null)
            ->orderBy('se.created_at', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $categoryCounts = $db->table('listing l')
            ->select('l.category, COUNT(*) as listing_count')
            ->join('sale_event se', 'se.listing_id = l.id')
            ->whereIn('se.status', ['active', 'grace_period', 'closed_sold'])
            ->groupBy('l.category')
            ->orderBy('listing_count', 'DESC')
            ->limit(6)
            ->get()->getResultArray();

        $totalActiveCount = $db->table('sale_event')->whereIn('status', ['active', 'grace_period'])->countAllResults();

        return view('landing', [
            'title' => 'eBid Hub — Salvage & Surplus Marketplace',
            'activeListings' => $activeListings,
            'categoryCounts' => $categoryCounts,
            'totalActiveCount' => $totalActiveCount,
        ]);
    }

    // Was missing entirely — the landing page only ever showed the 12
    // most recent active listings, with no way to see everything live
    // or filter by category/format.
    // Phase 3C: the real marketplace discovery page — filters, sort,
    // pagination, and a CLV preference-match badge — reachable as both
    // /browse (original route, unchanged) and /listings (the name the
    // pending-work document used), same alias pattern as /profile↔/account.
    public function browse(): string
    {
        $db = \Config\Database::connect();
        $category = $this->request->getGet('category');
        $format = $this->request->getGet('format');
        $priceMin = $this->request->getGet('price_min');
        $priceMax = $this->request->getGet('price_max');
        $location = $this->request->getGet('location');
        $minRating = $this->request->getGet('min_rating');
        $condition = $this->request->getGet('condition');
        $posted = $this->request->getGet('posted'); // '24h' | '7d' | '30d'
        $sort = $this->request->getGet('sort') ?: 'recent';
        $q = $this->request->getGet('q');
        $pg = Paginator::fromRequest($this->request, 24);

        // Phase 3C+: search history — only logged when the visitor is
        // identified AND genuinely searched for something (at least one
        // real filter set), not on every bare page load.
        $activeFilters = array_filter(compact('category', 'format', 'priceMin', 'priceMax', 'location', 'minRating', 'condition', 'posted', 'q'));
        $searchingPartyId = session()->get('logged_in_party_id');
        if ($searchingPartyId && !empty($activeFilters)) {
            (new \App\Models\SearchHistoryModel())->record($searchingPartyId, $activeFilters);
        }

        // COALESCE gives one comparable/sortable price across formats —
        // Easy/Express/Tender use current_price/reserve_value,
        // Buy-Now uses expected_value until a deal closes.
        $priceExpr = 'COALESCE(se.current_price, se.reserve_value, se.expected_value)';

        $filtered = function () use ($db, $category, $format, $priceMin, $priceMax, $location, $minRating, $condition, $posted, $q, $priceExpr) {
            $query = $db->table('sale_event se')
                ->join('listing l', 'l.id = se.listing_id')
                ->join('party p', 'p.id = l.seller_party_id')
                ->whereIn('se.status', ['active', 'grace_period'])
                // BR-38: Shadow Banning is graduated visibility
                // suppression, not an outright block — a shadow-banned
                // seller's listing is excluded from discovery/browse
                // only; it remains directly reachable by its own URL.
                ->where('p.shadow_banned_at_seller', null);

            if ($category) $query->where('l.category', $category);
            if ($format) $query->where('se.sale_format', $format);
            if ($condition) $query->where('l.physical_condition', $condition);
            if ($q) $query->groupStart()->like('l.category', $q)->orLike('l.subcategory', $q)->groupEnd();
            // Approximation, flagged: no discrete state column exists on
            // listing (only a free-text yard address + PIN) — a real
            // state dropdown would need that structured data added
            // first. Text search against the address is the honest
            // interim behavior, not a fabricated dropdown.
            if ($location) $query->like('l.yard_location_address', $location);
            if ($minRating) $query->where('p.seller_star_rating >=', (float) $minRating);
            if ($posted) {
                $cutoff = match ($posted) {
                    '24h' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    '7d' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    '30d' => date('Y-m-d H:i:s', strtotime('-30 days')),
                    default => null,
                };
                if ($cutoff) $query->where('l.created_at >=', $cutoff);
            }
            if ($priceMin !== null && $priceMin !== '') $query->where("{$priceExpr} >=", (float) $priceMin, false);
            if ($priceMax !== null && $priceMax !== '') $query->where("{$priceExpr} <=", (float) $priceMax, false);

            return $query;
        };

        $total = $filtered()->countAllResults();

        $query = $filtered()->select("se.id as sale_event_id, se.ern, se.sale_format, se.current_price, se.reserve_value,
                      se.expected_value, se.scheduled_end_at, l.id as listing_id, l.category, l.subcategory,
                      l.physical_condition, p.seller_star_rating, lm.file_path as photo_path, {$priceExpr} as display_price")
            ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false);

        match ($sort) {
            'ending_soon' => $query->orderBy('se.scheduled_end_at IS NULL, se.scheduled_end_at', 'ASC', false),
            'price_low' => $query->orderBy('display_price', 'ASC'),
            'price_high' => $query->orderBy('display_price', 'DESC'),
            'rating' => $query->orderBy('p.seller_star_rating', 'DESC'),
            default => $query->orderBy('l.created_at', 'DESC'),
        };

        $listings = $query->limit($pg['perPage'], $pg['offset'])->get()->getResultArray();

        // BR-23: mark which of this page's results match the logged-in
        // buyer's saved CLV preferences — reuses ClvMatchingService's
        // own match set rather than re-deriving separate logic.
        $matchedSaleEventIds = [];
        $buyerId = session()->get('logged_in_party_id');
        if ($buyerId) {
            $matches = (new \App\Libraries\ClvMatchingService())->findMatches($buyerId, 200);
            $matchedSaleEventIds = array_column($matches, 'sale_event_id');
        }
        foreach ($listings as &$item) {
            $item['clvMatch'] = in_array($item['sale_event_id'], $matchedSaleEventIds, true);
        }
        unset($item);

        $allCategories = $db->table('listing l')
            ->distinct()
            ->select('l.category')
            ->join('sale_event se', 'se.listing_id = l.id')
            ->whereIn('se.status', ['active', 'grace_period'])
            ->orderBy('l.category', 'ASC')
            ->get()->getResultArray();

        return view('browse', [
            'title' => 'Browse — eBid Hub', 'listings' => $listings,
            'allCategories' => array_column($allCategories, 'category'),
            'selectedCategory' => $category, 'selectedFormat' => $format,
            'priceMin' => $priceMin, 'priceMax' => $priceMax, 'location' => $location,
            'minRating' => $minRating, 'condition' => $condition, 'posted' => $posted, 'sort' => $sort, 'q' => $q,
            'page' => $pg['page'], 'perPage' => $pg['perPage'], 'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }
}
