<?php

namespace App\Libraries;

// D-106: real consolidation behind "Buyer Dashboard" / "Seller
// Dashboard" -- both existed only as functionality scattered across
// 5 separate real pages each (My Bids/Offers/Purchases/Profile/
// Earnings for buyers; My Listings/Sales/Earnings/Payout Bank/
// Invoices for sellers), never as one summary screen. This pulls a
// real, bounded summary from each existing source rather than
// reinventing any of them -- each section links out to its own full
// page, which already exists and already works.
class DashboardService
{
    public function buyerSummary(string $partyId): array
    {
        $db = \Config\Database::connect();

        $activeBids = $db->table('bid b')
            ->select("b.id, b.amount, b.standing, se.id as sale_event_id, se.sale_format, se.status as sale_status, l.category")
            ->join('sale_event se', 'se.id = b.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('b.bidder_party_id', $partyId)
            ->whereIn('b.standing', ['h1', 'h2', 'h3'])
            ->whereIn('se.status', ['active', 'grace_period'])
            ->orderBy('b.placed_at', 'DESC')
            ->limit(5)->get()->getResultArray();

        $openOffers = $db->table('offer o')
            ->select('o.id, o.amount, se.id as sale_event_id, l.category')
            ->join('sale_event se', 'se.id = o.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('o.buyer_party_id', $partyId)
            ->where('o.status', 'submitted')
            ->orderBy('o.created_at', 'DESC')
            ->limit(5)->get()->getResultArray();

        // BR-33: mandatory bidirectional rating -- a completed settlement
        // this buyer hasn't yet rated the seller on.
        $purchasesToRate = $db->table('settlement s')
            ->select('s.id, s.final_price, l.category')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('s.buyer_party_id', $partyId)
            ->where('s.buyer_rated_seller_at', null)
            ->orderBy('s.created_at', 'DESC')
            ->limit(5)->get()->getResultArray();

        $favoriteCount = (new \App\Models\ListingFavoriteModel())->where('party_id', $partyId)->countAllResults();

        return [
            'activeBidsCount' => $db->table('bid b')->join('sale_event se', 'se.id = b.sale_event_id')
                ->where('b.bidder_party_id', $partyId)->whereIn('b.standing', ['h1', 'h2', 'h3'])
                ->whereIn('se.status', ['active', 'grace_period'])->countAllResults(),
            'activeBids' => $activeBids,
            'openOffersCount' => $db->table('offer')->where('buyer_party_id', $partyId)->where('status', 'submitted')->countAllResults(),
            'openOffers' => $openOffers,
            'purchasesToRateCount' => $db->table('settlement')->where('buyer_party_id', $partyId)->where('buyer_rated_seller_at', null)->countAllResults(),
            'purchasesToRate' => $purchasesToRate,
            'favoriteCount' => $favoriteCount,
        ];
    }

    public function sellerSummary(string $partyId): array
    {
        $db = \Config\Database::connect();
        $monthStart = date('Y-m-01 00:00:00');

        $activeListings = $db->table('listing')
            ->select('id, category, subcategory, view_count')
            ->where('seller_party_id', $partyId)->where('status', 'active')
            ->orderBy('created_at', 'DESC')->limit(5)->get()->getResultArray();

        $pendingSettlements = $db->table('settlement s')
            ->select('s.id, s.final_price, l.category')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('s.seller_party_id', $partyId)
            ->where('s.status !=', 'completed')
            ->orderBy('s.created_at', 'DESC')->limit(5)->get()->getResultArray();

        $salesThisMonth = $db->table('settlement')
            ->selectSum('final_price', 'total')->selectCount('id', 'count')
            ->where('seller_party_id', $partyId)->where('status', 'completed')
            ->where('completed_at >=', $monthStart)
            ->get()->getRowArray();

        $party = (new \App\Models\PartyModel())->find($partyId);
        $payoutBankSet = !empty($party['payout_bank_account_number']);
        $payoutBankPending = !empty($party['payout_bank_pending_account_number']);

        $recentInvoices = (new InvoiceService())->findForParty($partyId, 5, 0);

        return [
            'activeListingsCount' => $db->table('listing')->where('seller_party_id', $partyId)->where('status', 'active')->countAllResults(),
            'activeListings' => $activeListings,
            'pendingSettlementsCount' => $db->table('settlement')->where('seller_party_id', $partyId)->where('status !=', 'completed')->countAllResults(),
            'pendingSettlements' => $pendingSettlements,
            'salesThisMonthCount' => (int) ($salesThisMonth['count'] ?? 0),
            'salesThisMonthTotal' => (float) ($salesThisMonth['total'] ?? 0),
            'payoutBankSet' => $payoutBankSet,
            'payoutBankPending' => $payoutBankPending,
            'recentInvoices' => $recentInvoices,
        ];
    }
}
