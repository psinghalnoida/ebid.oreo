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

    // Phase 3C+: favorites/watchlist — a plain toggle, no approval or
    // ownership check needed beyond being logged in (favoriting is
    // purely personal, unlike bidding/offering).
    public function favorite(string $listingId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        if (!$this->listingModel->find($listingId)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        (new \App\Models\ListingFavoriteModel())->add($partyId, $listingId);
        return redirect()->back();
    }

    public function unfavorite(string $listingId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        (new \App\Models\ListingFavoriteModel())->remove($partyId, $listingId);
        return redirect()->back();
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

        // BR-15: "structurally barred from listing assets... under any
        // circumstance." Checked first, ahead of every other gate.
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($sellerId)) {
            return redirect()->to('/')->with('error', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never list an asset.');
        }

        // BR-55: full KYC verification is mandatory before a User's
        // first Listing, with no lower-value exemption.
        try {
            (new \App\Libraries\KycService())->requireVerifiedKyc($sellerId, 'creating a Listing');
        } catch (\RuntimeException $e) {
            return redirect()->to('/kyc')->with('error', $e->getMessage());
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

        // BR-24: shipping is always optional for the buyer regardless
        // of this setting — a self-collection path is never removed.
        $shippingEnabled = $this->request->getPost('shipping_enabled') === '1';
        $shippingCostType = $shippingEnabled ? $this->request->getPost('shipping_cost_type') : null;
        if ($shippingEnabled && !in_array($shippingCostType, ['fixed', 'variable'], true)) {
            return view('listing/create', [
                'title' => 'List an Asset — eBid Hub',
                'tenants' => $this->tenantModel->findAll(),
                'error' => 'BR-24: choose either a Fixed or Variable shipping cost if shipping is enabled.',
            ]);
        }

        // BR-07: the listing category must come from the platform's own
        // closed list — new retail-consumer goods are explicitly
        // prohibited by the same rule. Checked server-side, not trusted
        // from the form (the dropdown already constrains it client-side,
        // but a raw POST could bypass that).
        $category = $this->request->getPost('category');
        if (!in_array($category, ListingLifecycleService::PERMITTED_CATEGORIES, true)) {
            return view('listing/create', [
                'title' => 'List an Asset — eBid Hub',
                'tenants' => $this->tenantModel->findAll(),
                'error' => 'BR-07: category must be one of the platform\'s permitted categories.',
            ]);
        }

        // BR-60: representative imagery can only be selected under a
        // genuinely active, approved waiver for this tenant+category —
        // checked server-side, not trusted from the form.
        $wantsRepresentativeMedia = $this->request->getPost('media_is_representative_under_waiver') === '1';
        $representativeMediaFlag = false;
        if ($wantsRepresentativeMedia) {
            $hasWaiver = (new \App\Libraries\TenantMediaWaiverService())->isCbsProhibitionWaived($tenantId, $category);
            if (!$hasWaiver) {
                return view('listing/create', [
                    'title' => 'List an Asset — eBid Hub',
                    'tenants' => $this->tenantModel->findAll(),
                    'error' => 'BR-60: this tenant has no active media waiver for this category — representative imagery cannot be used.',
                ]);
            }
            $representativeMediaFlag = true;
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
                'shipping_enabled' => $shippingEnabled,
                'shipping_cost_type' => $shippingCostType,
                'shipping_fixed_cost' => $shippingCostType === 'fixed' ? (float) $this->request->getPost('shipping_fixed_cost') : null,
                'shipping_variable_rate_per_km' => $shippingCostType === 'variable' ? (float) $this->request->getPost('shipping_variable_rate_per_km') : null,
                'media_is_representative_under_waiver' => $representativeMediaFlag,
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
        // PR-09: files still in the background queue (pending/processing/
        // failed) — shown alongside finished media so the seller sees real
        // upload progress instead of files silently vanishing until done.
        $queuedMediaJobs = (new \App\Libraries\MediaService())->getQueuedJobsForListing($listingId);
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

        // BR-41: "the seller's own sellerStarRating remains visible to all
        // bidding buyers throughout the live event" — even in the fully
        // anonymous Easy/Express formats. Not a breach of bidder anonymity
        // (BR-16): this exposes only the seller's own public reputation
        // number, never their identity.
        $seller = (new \App\Models\PartyModel())->find($listing['seller_party_id']);
        $sellerStarRating = $seller ? (float) $seller['seller_star_rating'] : null;

        $viewerId = session()->get('logged_in_party_id');
        // BR-32 (D-87/D-88): gates whether the Fee Payer Election's
        // Seller-Pays option is offered on the sale-event attach forms.
        $tenant = $this->tenantModel->find($listing['tenant_id']);
        return view('listing/show', [
            'title' => 'Listing — eBid Hub', 'listing' => $listing, 'saleEvent' => $saleEvent, 'tenant' => $tenant,
            'offers' => $offers, 'expressState' => $expressState, 'tenderState' => $tenderState, 'media' => $media,
            'queuedMediaJobs' => $queuedMediaJobs,
            'isOwner' => $viewerId === $listing['seller_party_id'],
            'minPhotos' => \App\Libraries\MediaService::minPhotos(),
            'rejectionReasons' => \App\Libraries\ListingLifecycleService::REJECTION_REASONS,
            'settlementRecord' => $settlementRecord,
            'relatedListings' => $relatedListings,
            'isFavorited' => $viewerId ? (new \App\Models\ListingFavoriteModel())->isFavorited($viewerId, $listingId) : false,
            'sellerStarRating' => $sellerStarRating,
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
        $reasonKey = (string) $this->request->getPost('reason_key');
        $detail = $this->request->getPost('detail') ?: null;
        try {
            $this->lifecycle->reject($listingId, $reasonKey, $detail, session()->get('logged_in_party_id'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$listingId}")->with('error', $e->getMessage());
        }
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

        // BR-07: same closed-list enforcement as creation — an edit can't
        // move a listing into a prohibited category either.
        $newCategory = $this->request->getPost('category') ?: $listing['category'];
        if (!in_array($newCategory, ListingLifecycleService::PERMITTED_CATEGORIES, true)) {
            return redirect()->to("/listings/{$listingId}")->with('error', 'BR-07: category must be one of the platform\'s permitted categories.');
        }

        $newData = [
            'physical_condition' => $this->request->getPost('physical_condition') ?: $listing['physical_condition'],
            'category' => $newCategory,
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

    // BR-59/BR-61: CBS violations require manual flagging — automated
    // stock-photo detection is confirmed out of scope (D-59). Available
    // to the Tenant Admin for the listing's own tenant, or Super Admin.
    public function flagCbsViolation(string $listingId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $listing = $this->listingModel->find($listingId);
        if (!$listing) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $authz = new \App\Libraries\AuthorizationService();
        if (!$authz->isTenantAdminFor($partyId, $listing['tenant_id']) && !$authz->isSuperAdmin($partyId)) {
            return redirect()->to("/listings/{$listingId}")->with('error', 'Only this listing\'s Tenant Admin or Super Admin may flag a CBS violation.');
        }

        $result = (new \App\Libraries\StandingReviewService())->recordCbsViolation($listing['seller_party_id'], $partyId, $listingId);

        return redirect()->to("/listings/{$listingId}")->with('error',
            "CBS violation logged — offense #{$result['offenseNumber']}, tier: {$result['tier']}."
        );
    }
}
