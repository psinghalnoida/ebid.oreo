<?php

namespace App\Controllers;

use App\Libraries\Paginator;
use App\Libraries\UserAuthContext;

class MyActivityController extends BaseController
{
    // Phase 3A: a real, dedicated, paginated/filterable bid history.
    public function myBids()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $format = $this->request->getGet('format');
        $status = $this->request->getGet('status');
        $sort = $this->request->getGet('sort') ?: 'recent';
        $pg = Paginator::fromRequest($this->request);

        $filtered = function () use ($db, $partyId, $format, $status) {
            $q = $db->table('bid b')
                ->join('sale_event se', 'se.id = b.sale_event_id')
                ->join('listing l', 'l.id = se.listing_id')
                ->where('b.bidder_party_id', $partyId);
            if ($format) $q->where('se.sale_format', $format);
            if ($status) $q->where('b.standing', $status);
            return $q;
        };

        $total = $filtered()->countAllResults();
        $query = $filtered()->select('b.id, b.amount, b.standing, b.placed_at, se.id as sale_event_id, se.sale_format, se.status as sale_status, l.id as listing_id, l.category');
        match ($sort) {
            'oldest' => $query->orderBy('b.placed_at', 'ASC'),
            'highest' => $query->orderBy('b.amount', 'DESC'),
            'lowest' => $query->orderBy('b.amount', 'ASC'),
            default => $query->orderBy('b.placed_at', 'DESC'),
        };
        $bids = $query->limit($pg['perPage'], $pg['offset'])->get()->getResultArray();

        return $this->apiResponse([
            'bids' => $bids, 'format' => $format, 'status' => $status, 'sort' => $sort,
            'page' => $pg['page'], 'perPage' => $pg['perPage'], 'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    public function myOffers()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $status = $this->request->getGet('status');
        $pg = Paginator::fromRequest($this->request);

        $filtered = function () use ($db, $partyId, $status) {
            $q = $db->table('offer o')
                ->join('sale_event se', 'se.id = o.sale_event_id')
                ->join('listing l', 'l.id = se.listing_id')
                ->where('o.buyer_party_id', $partyId);
            if ($status) $q->where('o.status', $status);
            return $q;
        };

        $total = $filtered()->countAllResults();
        $offers = $filtered()->select('o.id, o.amount, o.status, o.created_at, se.id as sale_event_id, l.id as listing_id, l.category')
            ->orderBy('o.created_at', 'DESC')->limit($pg['perPage'], $pg['offset'])->get()->getResultArray();

        return $this->apiResponse([
            'offers' => $offers, 'status' => $status,
            'page' => $pg['page'], 'perPage' => $pg['perPage'], 'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    // BR-16: after close, the buyer's and seller's real identities are
    // meant to be visible to each other (EMD pledge / offer acceptance
    // unlocks it) — NOT masked. A settlement only exists post-close, so
    // showing the real counter-party here is correct per BR-16's own
    // text, not an over-exposure.
    public function myPurchases()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $format = $this->request->getGet('format');
        $status = $this->request->getGet('status');
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        $pg = Paginator::fromRequest($this->request);

        $filtered = function () use ($db, $partyId, $format, $status, $from, $to) {
            $q = $db->table('settlement s')
                ->join('sale_event se', 'se.id = s.sale_event_id')
                ->join('listing l', 'l.id = se.listing_id')
                ->join('party seller', 'seller.id = s.seller_party_id')
                ->where('s.buyer_party_id', $partyId);
            if ($format) $q->where('se.sale_format', $format);
            if ($status) $q->where('s.status', $status);
            if ($from) $q->where('s.created_at >=', $from . ' 00:00:00');
            if ($to) $q->where('s.created_at <=', $to . ' 23:59:59');
            return $q;
        };

        $total = $filtered()->countAllResults();
        $purchases = $filtered()
            ->select('s.id, s.sale_event_id, s.final_price, s.status, s.created_at, s.buyer_rated_seller_at, se.sale_format, l.category, seller.mobile_number as seller_mobile')
            ->orderBy('s.created_at', 'DESC')->limit($pg['perPage'], $pg['offset'])->get()->getResultArray();

        $saleEventIds = array_column($purchases, 'sale_event_id');
        $disputesBySaleEvent = [];
        if (!empty($saleEventIds)) {
            foreach ($db->table('dispute')->whereIn('sale_event_id', $saleEventIds)->get()->getResultArray() as $d) {
                $disputesBySaleEvent[$d['sale_event_id']] = $d;
            }
        }
        foreach ($purchases as &$p) {
            $p['dispute'] = $disputesBySaleEvent[$p['sale_event_id']] ?? null;
        }
        unset($p);

        return $this->apiResponse([
            'purchases' => $purchases,
            'format' => $format, 'status' => $status, 'from' => $from, 'to' => $to,
            'page' => $pg['page'], 'perPage' => $pg['perPage'], 'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    // CSV download — deliberately kept as a raw file response, not JSON;
    // React triggers this with a fetch() + Bearer header, turning the
    // response body into a Blob for the browser to save (a plain <a
    // href> can't carry an Authorization header the way a query-param
    // token would, so this is not a plain-link download).
    public function myPurchasesExport()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $rows = $db->table('settlement s')
            ->select('s.created_at, s.final_price, s.status, se.sale_format, l.category, seller.mobile_number as seller_mobile')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party seller', 'seller.id = s.seller_party_id')
            ->where('s.buyer_party_id', $partyId)
            ->orderBy('s.created_at', 'DESC')->get()->getResultArray();

        return $this->csvResponse('my-purchases.csv', ['Date', 'Category', 'Format', 'Price', 'Status', 'Seller Mobile'],
            array_map(fn($r) => [substr($r['created_at'], 0, 10), $r['category'], strtoupper($r['sale_format']), $r['final_price'], $r['status'], $r['seller_mobile']], $rows));
    }

    public function mySales()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $format = $this->request->getGet('format');
        $status = $this->request->getGet('status');
        $from = $this->request->getGet('from');
        $to = $this->request->getGet('to');
        $pg = Paginator::fromRequest($this->request);

        $filtered = function () use ($db, $partyId, $format, $status, $from, $to) {
            $q = $db->table('settlement s')
                ->join('sale_event se', 'se.id = s.sale_event_id')
                ->join('listing l', 'l.id = se.listing_id')
                ->join('party buyer', 'buyer.id = s.buyer_party_id')
                ->join('emd_hold eh', 'eh.sale_event_id = s.sale_event_id AND eh.party_id = s.buyer_party_id', 'left')
                ->where('s.seller_party_id', $partyId);
            if ($format) $q->where('se.sale_format', $format);
            if ($status) $q->where('s.status', $status);
            if ($from) $q->where('s.created_at >=', $from . ' 00:00:00');
            if ($to) $q->where('s.created_at <=', $to . ' 23:59:59');
            return $q;
        };

        $total = $filtered()->countAllResults();
        $sales = $filtered()
            ->select('s.id, s.final_price, s.status, s.created_at, se.sale_format, l.category, buyer.mobile_number as buyer_mobile,
                      COALESCE(eh.forfeited_to_tenant_amount, 0) + COALESCE(eh.forfeited_to_saas_amount, 0) as fee_deducted,
                      COALESCE(s.tds_amount, 0) as tds_amount')
            ->orderBy('s.created_at', 'DESC')->limit($pg['perPage'], $pg['offset'])->get()->getResultArray();

        return $this->apiResponse([
            'sales' => $sales,
            'format' => $format, 'status' => $status, 'from' => $from, 'to' => $to,
            'page' => $pg['page'], 'perPage' => $pg['perPage'], 'totalPages' => Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    public function mySalesExport()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $rows = $db->table('settlement s')
            ->select('s.created_at, s.final_price, s.status, se.sale_format, l.category, buyer.mobile_number as buyer_mobile,
                      COALESCE(eh.forfeited_to_tenant_amount, 0) + COALESCE(eh.forfeited_to_saas_amount, 0) as fee_deducted,
                      COALESCE(s.tds_amount, 0) as tds_amount')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->join('party buyer', 'buyer.id = s.buyer_party_id')
            ->join('emd_hold eh', 'eh.sale_event_id = s.sale_event_id AND eh.party_id = s.buyer_party_id', 'left')
            ->where('s.seller_party_id', $partyId)
            ->orderBy('s.created_at', 'DESC')->get()->getResultArray();

        return $this->csvResponse('my-sales.csv', ['Date', 'Category', 'Format', 'Price', 'Fee Deducted', 'TDS Deducted', 'Status', 'Buyer Mobile'],
            array_map(fn($r) => [substr($r['created_at'], 0, 10), $r['category'], strtoupper($r['sale_format']), $r['final_price'], $r['fee_deducted'], $r['tds_amount'], $r['status'], $r['buyer_mobile']], $rows));
    }

    private function csvResponse(string $filename, array $header, array $rows)
    {
        $this->response->setHeader('Content-Type', 'text/csv');
        $this->response->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $this->response->setBody($csv);
    }

    public function myListings()
    {
        $partyId = UserAuthContext::partyId();

        $db = \Config\Database::connect();
        $listings = $db->table('listing l')
            ->select('l.id, l.category, l.subcategory, l.status, l.physical_condition, l.created_at,
                      se.sale_format, se.current_price, se.reserve_value, se.expected_value')
            ->join('sale_event se', 'se.listing_id = l.id', 'left')
            ->where('l.seller_party_id', $partyId)
            ->orderBy('l.created_at', 'DESC')
            ->get()->getResultArray();

        return $this->apiResponse(['listings' => $listings]);
    }

    public function myActivity()
    {
        $partyId = UserAuthContext::partyId();

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

        return $this->apiResponse(['bids' => $bids, 'offers' => $offers, 'settlements' => $settlements]);
    }

    public function profile()
    {
        $party = (new \App\Models\PartyModel())->find(UserAuthContext::partyId());
        return $this->apiResponse(['party' => $party]);
    }

    // D-105: the buyer-side half of Lot Reach & Interest — a real inbox
    // for messages a Market Maker sent because this buyer matched one of
    // their listings.
    public function messages()
    {
        $recipientModel = new \App\Models\SellerMessageRecipientModel();
        $messages = $recipientModel->findForBuyer(UserAuthContext::partyId());
        return $this->apiResponse(['messages' => $messages]);
    }

    public function markMessageRead(string $recipientId)
    {
        (new \App\Models\SellerMessageRecipientModel())->markRead($recipientId, UserAuthContext::partyId());
        return $this->apiResponse(['marked' => true]);
    }

    // D-106: "Star Ratings" -- a party's current standing in both roles.
    public function starRatings()
    {
        $party = (new \App\Models\PartyModel())->find(UserAuthContext::partyId());
        return $this->apiResponse(['party' => $party]);
    }

    // D-106: "Rating History" -- the real rating_event audit trail.
    public function ratingHistory()
    {
        $events = (new \App\Models\RatingEventModel())->findForParty(UserAuthContext::partyId());
        return $this->apiResponse(['events' => $events]);
    }

    // D-106: "Buyer Dashboard" -- a real consolidation of My Bids/
    // Offers/Purchases-to-rate/Favorites into one summary.
    public function buyerDashboard()
    {
        $summary = (new \App\Libraries\DashboardService())->buyerSummary(UserAuthContext::partyId());
        return $this->apiResponse(['summary' => $summary]);
    }

    // D-106: "Seller Dashboard" -- same consolidation, seller side.
    public function sellerDashboard()
    {
        $summary = (new \App\Libraries\DashboardService())->sellerSummary(UserAuthContext::partyId());
        return $this->apiResponse(['summary' => $summary]);
    }
}
