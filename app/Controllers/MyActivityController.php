<?php

namespace App\Controllers;

class MyActivityController extends BaseController
{
    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function myListings()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $db = \Config\Database::connect();
        $listings = $db->table('listing l')
            ->select('l.id, l.category, l.subcategory, l.status, l.physical_condition, l.created_at,
                      se.sale_format, se.current_price, se.reserve_value, se.expected_value')
            ->join('sale_event se', 'se.listing_id = l.id', 'left')
            ->where('l.seller_party_id', $partyId)
            ->orderBy('l.created_at', 'DESC')
            ->get()->getResultArray();

        return view('my/listings', ['title' => 'My Listings — eBid Hub', 'listings' => $listings]);
    }

    public function myActivity()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $db = \Config\Database::connect();

        $bids = $db->table('bid b')
            ->select('b.amount, b.standing, b.placed_at, se.id as sale_event_id, se.sale_format, se.status as sale_status, l.id as listing_id, l.category')
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('b.bidder_party_id', $partyId)
            ->orderBy('b.placed_at', 'DESC')
            ->get()->getResultArray();

        $offers = $db->table('offer o')
            ->select('o.amount, o.status, o.created_at, se.id as sale_event_id, l.id as listing_id, l.category')
            ->join('sale_event se', 'se.id = o.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('o.buyer_party_id', $partyId)
            ->orderBy('o.created_at', 'DESC')
            ->get()->getResultArray();

        $settlements = $db->table('settlement s')
            ->select('s.id, s.status, s.final_price, l.category')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('s.buyer_party_id', $partyId)
            ->orderBy('s.created_at', 'DESC')
            ->get()->getResultArray();

        return view('my/activity', [
            'title' => 'My Activity — eBid Hub', 'bids' => $bids, 'offers' => $offers, 'settlements' => $settlements,
        ]);
    }

    public function profile()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $party = (new \App\Models\PartyModel())->find($partyId);
        return view('my/profile', ['title' => 'My Profile — eBid Hub', 'party' => $party]);
    }
}
