<?php

namespace App\Controllers;

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
            ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false)
            ->whereIn('se.status', ['active', 'grace_period'])
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
    public function browse(): string
    {
        $db = \Config\Database::connect();
        $category = $this->request->getGet('category');
        $format = $this->request->getGet('format');

        $query = $db->table('sale_event se')
            ->select('se.id as sale_event_id, se.ern, se.sale_format, se.current_price, se.reserve_value, se.expected_value,
                      l.id as listing_id, l.category, l.subcategory, l.physical_condition,
                      lm.file_path as photo_path')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false)
            ->whereIn('se.status', ['active', 'grace_period']);

        if ($category) {
            $query->where('l.category', $category);
        }
        if ($format) {
            $query->where('se.sale_format', $format);
        }

        $listings = $query->orderBy('se.created_at', 'DESC')->get()->getResultArray();

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
        ]);
    }
}
