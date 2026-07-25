<?php

namespace App\Controllers;

use App\Libraries\ClvMatchingService;

class LiveTickerController extends BaseController
{
    public function feed()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return $this->response->setJSON(['ownBids' => [], 'interestMatches' => []]);
        }

        $db = \Config\Database::connect();

        // A buyer's "own active bids" spans two separate tables —
        // `bid` (Easy/Express/Tender) and `offer` (Buy-Now) — found
        // and fixed before this ever shipped: the first version of this
        // query only checked `bid`, silently missing every Buy-Now
        // commitment a buyer has live.
        $bidRows = $db->table('bid b')
            ->select("b.sale_event_id, b.amount, b.standing, se.ern, se.sale_format,
                      se.scheduled_end_at, l.category, l.id as listing_id, b.placed_at as activity_at")
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('b.bidder_party_id', $partyId)
            ->whereIn('se.status', ['active', 'grace_period'])
            ->get()->getResultArray();

        $offerRows = $db->table('offer o')
            ->select("o.sale_event_id, o.amount, o.status as standing, se.ern, se.sale_format,
                      se.scheduled_end_at, l.category, l.id as listing_id, o.created_at as activity_at")
            ->join('sale_event se', 'se.id = o.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('o.buyer_party_id', $partyId)
            ->where('o.status', 'submitted')
            ->whereIn('se.status', ['active', 'grace_period'])
            ->get()->getResultArray();

        // One row per sale_event_id, most recent activity first — a
        // buyer only ever has one live commitment per auction anyway.
        $combined = [];
        foreach (array_merge($bidRows, $offerRows) as $row) {
            $existing = $combined[$row['sale_event_id']] ?? null;
            if (!$existing || $row['activity_at'] > $existing['activity_at']) {
                $combined[$row['sale_event_id']] = $row;
            }
        }
        $ownBids = array_values($combined);

        $rating = new \App\Libraries\RatingService();
        $isShadowBanned = $rating->isShadowBanned($partyId, 'star_rating');

        $interestMatches = $isShadowBanned ? [] : (new ClvMatchingService())->findMatches($partyId);

        return $this->response->setJSON(['ownBids' => $ownBids, 'interestMatches' => $interestMatches]);
    }
}
