<?php

namespace App\Controllers;

use App\Libraries\ListingLifecycleService;
use App\Models\ListingModel;
use App\Models\TenantModel;

class ListingController extends BaseController
{
    private ListingLifecycleService $lifecycle;
    private ListingModel $listingModel;
    private TenantModel $tenantModel;

    public function __construct()
    {
        $this->lifecycle = new ListingLifecycleService();
        $this->listingModel = new ListingModel();
        $this->tenantModel = new TenantModel();
    }

    private function requireLogin()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return null;
        }
        return $partyId;
    }

    public function createForm()
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }
        // Dev convenience: for now, list any tenant to attach to.
        // Tenant selection/scoping by seller role (BR-09) is not yet built.
        $tenants = $this->tenantModel->findAll();
        return view('listing/create', ['title' => 'List an Asset — eBid Hub', 'tenants' => $tenants]);
    }

    public function createSubmit()
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }

        $tenantId = $this->request->getPost('tenant_id');

        // BR-38: a delisted seller (confirmed fraud) cannot list on ANY
        // tenant — checked before the tenant-specific BR-09 gate below,
        // since this is a platform-wide restriction, not per-tenant.
        if ((new \App\Libraries\RatingService())->isDelisted($sellerId)) {
            return redirect()->to('/')->with('error', 'BR-38: this account has been delisted from selling on eBid Hub due to a confirmed fraud finding.');
        }

        // BR-09: only a party the Tenant Admin has explicitly upgraded to
        // Seller on THIS specific tenant may list here.
        $sellerApp = new \App\Libraries\SellerApplicationService();
        if (!$sellerApp->isApprovedSeller($sellerId, $tenantId)) {
            return redirect()->to("/tenants/{$tenantId}/apply-to-sell")
                ->with('error', 'BR-09: you must be an approved Seller on this specific tenant before listing here.');
        }

        // BR-11/BR-21: bind up to three inspection-authority roles, each
        // by mobile number (resolved to a party ID) — all optional, per
        // BR-11's minimal case ("for direct owner listings, the owner's
        // own contact"). Was never actually captured through any form
        // until now — found during a full BR/PR audit.
        $partyModel = new \App\Models\PartyModel();
        $inspectorPartyId = null;
        $surveyorPartyId = null;
        $custodianPartyId = null;
        if ($mobile = $this->request->getPost('inspector_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $inspectorPartyId = $party['id'] ?? null;
        }
        if ($mobile = $this->request->getPost('surveyor_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $surveyorPartyId = $party['id'] ?? null;
        }
        if ($mobile = $this->request->getPost('custodian_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $custodianPartyId = $party['id'] ?? null;
        }

        // BR-47: a seller-provided label groups listings sharing a
        // common origin — purely navigational, zero effect on any
        // listing's own independent transaction. Matching is scoped to
        // the SAME seller (a shared label from a different seller is a
        // coincidence, not the same origin lot).
        $relatedGroupId = null;
        $relatedGroupLabel = trim((string) $this->request->getPost('related_group_label'));
        if ($relatedGroupLabel !== '') {
            $existingGroupMember = $this->listingModel
                ->where('seller_party_id', $sellerId)
                ->where('related_group_label', $relatedGroupLabel)
                ->first();
            $relatedGroupId = $existingGroupMember ? $existingGroupMember['related_group_id'] : \App\Libraries\Uuid::v4();
        }

        try {
            $listing = $this->listingModel->createListing([
                'tenant_id' => $tenantId,
                'seller_party_id' => $sellerId,
                'physical_condition' => $this->request->getPost('physical_condition'),
                'category' => $this->request->getPost('category'),
                'subcategory' => $this->request->getPost('subcategory') ?: null,
                'quantity' => $this->request->getPost('quantity'),
                'quantity_basis' => 'unit',
                'make_model' => $this->request->getPost('make_model'),
                'yard_location_address' => $this->request->getPost('yard_location_address'),
                'yard_location_pin' => $this->request->getPost('yard_location_pin'),
                'media_tier' => $this->request->getPost('media_tier') ?: 'certified_by_seller',
                'inspector_party_id' => $inspectorPartyId,
                'surveyor_party_id' => $surveyorPartyId,
                'custodian_party_id' => $custodianPartyId,
                'related_group_id' => $relatedGroupId,
                'related_group_label' => $relatedGroupLabel !== '' ? $relatedGroupLabel : null,
            ]);
        } catch (\Throwable $e) {
            return view('listing/create', [
                'title' => 'List an Asset — eBid Hub',
                'tenants' => $this->tenantModel->findAll(),
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->to("/listings/{$listing['id']}");
    }

    public function show(string $listingId)
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();
        $saleEvent = $db->table('sale_event')
            ->where('listing_id', $listingId)
            ->whereIn('status', ['pending_approval', 'grace_period', 'active', 'closed_sold'])
            ->orderBy('created_at', 'DESC')
            ->get()->getRowArray();

        $offers = [];
        $expressState = null;
        $settlementRecord = null;
        $media = (new \App\Models\ListingMediaModel())->findForListing($listingId);
        if ($saleEvent && $saleEvent['status'] === 'closed_sold') {
            $settlementRecord = (new \App\Models\SettlementModel())->findBySaleEvent($saleEvent['id']);
        }
        if ($saleEvent && $saleEvent['sale_format'] === 'buy_now') {
            $offerModel = new \App\Models\OfferModel();
            $offers = $offerModel->findForSaleEvent($saleEvent['id']);
        }
        if ($saleEvent && $saleEvent['sale_format'] === 'express') {
            $expressService = new \App\Libraries\ExpressAuctionService();
            $expressState = [
                'pledgeCount' => $expressService->pledgeCount($saleEvent['id']),
                'biddingOpen' => $expressService->isBiddingOpen($saleEvent),
            ];
        }

        $tenderState = null;
        if ($saleEvent && $saleEvent['sale_format'] === 'tender') {
            $callerId = session()->get('logged_in_party_id');
            $tenderService = new \App\Libraries\TenderService();
            $tenderBidding = new \App\Libraries\TenderBiddingService();
            $tenderReview = new \App\Libraries\TenderReviewService();
            $tenderState = [
                'isEligible' => $callerId ? $tenderService->isEligible($saleEvent['id'], $callerId) : false,
                'biddingOpen' => $tenderBidding->isBiddingOpen($saleEvent),
                'documents' => $tenderService->getDocuments($saleEvent['id']),
                'currentReview' => $tenderReview->getCurrentReview($saleEvent['id']),
            ];
        }

        // BR-47: related items, if this listing is part of a group —
        // purely a display concern, zero effect on this listing's own
        // independent bidding/EMD/settlement.
        $relatedListings = [];
        if (!empty($listing['related_group_id'])) {
            $db = \Config\Database::connect();
            $relatedListings = $db->table('listing l')
                ->select('l.id, l.category, l.subcategory, se.current_price, se.reserve_value, se.expected_value, se.status, se.sale_format, lm.file_path as photo_path')
                ->join('sale_event se', 'se.listing_id = l.id', 'left')
                ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false)
                ->where('l.related_group_id', $listing['related_group_id'])
                ->where('l.id !=', $listingId)
                ->get()->getResultArray();
        }

        return view('listing/show', [
            'title' => 'Listing — eBid Hub', 'listing' => $listing, 'saleEvent' => $saleEvent,
            'offers' => $offers, 'expressState' => $expressState, 'tenderState' => $tenderState, 'media' => $media,
            'isOwner' => session()->get('logged_in_party_id') === $listing['seller_party_id'],
            'minPhotos' => \App\Libraries\MediaService::minPhotos(),
            'settlementRecord' => $settlementRecord,
            'relatedListings' => $relatedListings,
        ]);
    }

    // BR-13: submit for Tenant Admin review
    public function submitForApproval(string $listingId)
    {
        try {
            $this->lifecycle->submitForApproval($listingId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$listingId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$listingId}");
    }

    // BR-09: Tenant Admin approval — access enforced by the tenantAdmin
    // route filter, not by this method. If execution reaches here, the
    // caller has already been confirmed as the Tenant Admin for this
    // listing's tenant.
    public function approve(string $listingId)
    {
        $this->lifecycle->approve($listingId, session()->get('logged_in_party_id'));
        return redirect()->to("/listings/{$listingId}");
    }

    public function reject(string $listingId)
    {
        $reason = $this->request->getPost('reason') ?: 'insufficient photos';
        $this->lifecycle->reject($listingId, $reason, session()->get('logged_in_party_id'));
        return redirect()->to("/listings/{$listingId}");
    }

    // Was fully built and tested (archive-and-recreate, refunds active
    // bids) but had no HTTP route at all until now.
    public function editSubmit(string $listingId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $listing = $this->listingModel->find($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return service('response')->setStatusCode(403)->setBody('Only the listing\'s seller may edit it.');
        }

        $newData = [
            'physical_condition' => $this->request->getPost('physical_condition') ?: $listing['physical_condition'],
            'category' => $this->request->getPost('category') ?: $listing['category'],
            'subcategory' => $this->request->getPost('subcategory') ?: $listing['subcategory'],
            'quantity' => $this->request->getPost('quantity') ?: $listing['quantity'],
            'quantity_basis' => $listing['quantity_basis'],
            'seller_party_id' => $partyId,
            'yard_location_address' => $this->request->getPost('yard_location_address') ?: $listing['yard_location_address'],
            'yard_location_pin' => $this->request->getPost('yard_location_pin') ?: $listing['yard_location_pin'],
        ];

        try {
            $result = $this->lifecycle->requestMaterialEdit($listingId, $newData);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$listingId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$result['newListing']['id']}")->with('error', 'Listing updated — this is a new listing record (archive-and-recreate per BR-13); any active bids on the old one were withdrawn and EMD released.');
    }
}
