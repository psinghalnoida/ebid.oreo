<?php

namespace App\Controllers;

use App\Libraries\GeminiPreAuditService;
use App\Libraries\ListingLifecycleService;
use App\Libraries\UserAuthContext;
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

    // Phase 3C+: favorites/watchlist — a plain toggle, no approval or
    // ownership check needed beyond being logged in (favoriting is
    // purely personal, unlike bidding/offering). Auth enforced by the
    // jwtAuth route filter.
    public function favorite(string $listingId)
    {
        $partyId = UserAuthContext::partyId();

        if (!$this->listingModel->find($listingId)) {
            return $this->jsonError(404, 'not_found', 'Listing not found.');
        }

        (new \App\Models\ListingFavoriteModel())->add($partyId, $listingId);
        return $this->apiResponse(['favorited' => true]);
    }

    public function unfavorite(string $listingId)
    {
        $partyId = UserAuthContext::partyId();
        (new \App\Models\ListingFavoriteModel())->remove($partyId, $listingId);
        return $this->apiResponse(['favorited' => false]);
    }

    // BR-46: a seller may trigger this before submitting -- purely
    // advisory, never gates or auto-approves anything.
    public function preAudit()
    {
        $draft = [
            'category' => $this->input('category'),
            'subcategory' => $this->input('subcategory'),
            'physicalCondition' => $this->input('physical_condition'),
            'quantity' => $this->input('quantity'),
            'quantityBasis' => $this->input('quantity_basis') ?? 'unit',
            'makeModel' => $this->input('make_model'),
        ];

        try {
            $result = (new GeminiPreAuditService())->evaluate($draft);
        } catch (\RuntimeException $e) {
            return $this->apiResponse(['available' => false, 'message' => $e->getMessage()], null, 503);
        }

        return $this->apiResponse(array_merge(['available' => true], $result));
    }

    public function createSubmit()
    {
        $sellerId = UserAuthContext::partyId();

        // BR-15: "structurally barred from listing assets... under any
        // circumstance." Checked first, ahead of every other gate.
        if ((new \App\Libraries\AuthorizationService())->isSuperAdmin($sellerId)) {
            return $this->jsonError(403, 'br15_super_admin_barred', 'BR-15: the Super Admin holds a non-participatory regulatory role and may never list an asset.');
        }

        // BR-55: full KYC verification is mandatory before a User's
        // first Listing, with no lower-value exemption.
        try {
            (new \App\Libraries\KycService())->requireVerifiedKyc($sellerId, 'creating a Listing');
        } catch (\RuntimeException $e) {
            return $this->jsonError(403, 'kyc_required', $e->getMessage());
        }

        $tenantId = $this->input('tenant_id');

        // BR-38: a delisted seller (confirmed fraud) cannot list on ANY
        // tenant — checked before the tenant-specific BR-09 gate below,
        // since this is a platform-wide restriction, not per-tenant.
        if ((new \App\Libraries\RatingService())->isDelisted($sellerId)) {
            return $this->jsonError(403, 'br38_delisted', 'BR-38: this account has been delisted from selling on AdwitiX due to a confirmed fraud finding.');
        }

        // BR-09: only a party the Tenant Admin has explicitly upgraded to
        // Seller on THIS specific tenant may list here.
        $sellerApp = new \App\Libraries\SellerApplicationService();
        if (!$sellerApp->isApprovedSeller($sellerId, $tenantId)) {
            return $this->jsonError(403, 'br09_not_approved_seller', 'BR-09: you must be an approved Seller on this specific tenant before listing here.');
        }

        // BR-11/BR-21: bind up to three inspection-authority roles, each
        // by mobile number (resolved to a party ID) — all optional.
        $partyModel = new \App\Models\PartyModel();
        $inspectorPartyId = null;
        $surveyorPartyId = null;
        $custodianPartyId = null;
        if ($mobile = $this->input('inspector_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $inspectorPartyId = $party['id'] ?? null;
        }
        if ($mobile = $this->input('surveyor_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $surveyorPartyId = $party['id'] ?? null;
        }
        if ($mobile = $this->input('custodian_mobile')) {
            $party = $partyModel->findByMobile($mobile);
            $custodianPartyId = $party['id'] ?? null;
        }

        // BR-47: a seller-provided label groups listings sharing a
        // common origin — purely navigational, zero effect on any
        // listing's own independent transaction. Matching is scoped to
        // the SAME seller (a shared label from a different seller is a
        // coincidence, not the same origin lot).
        $relatedGroupId = null;
        $relatedGroupLabel = trim((string) $this->input('related_group_label'));
        if ($relatedGroupLabel !== '') {
            $existingGroupMember = $this->listingModel
                ->where('seller_party_id', $sellerId)
                ->where('related_group_label', $relatedGroupLabel)
                ->first();
            $relatedGroupId = $existingGroupMember ? $existingGroupMember['related_group_id'] : \App\Libraries\Uuid::v4();
        }

        // BR-24: shipping is always optional for the buyer regardless
        // of this setting — a self-collection path is never removed.
        $shippingEnabled = (string) $this->input('shipping_enabled') === '1';
        $shippingCostType = $shippingEnabled ? $this->input('shipping_cost_type') : null;
        if ($shippingEnabled && !in_array($shippingCostType, ['fixed', 'variable'], true)) {
            return $this->jsonError(422, 'br24_invalid_shipping', 'BR-24: choose either a Fixed or Variable shipping cost if shipping is enabled.');
        }

        // BR-07: the listing category must come from the platform's own
        // closed list — new retail-consumer goods are explicitly
        // prohibited by the same rule. Checked server-side, not trusted
        // from the request body.
        $category = $this->input('category');
        if (!in_array($category, ListingLifecycleService::PERMITTED_CATEGORIES, true)) {
            return $this->jsonError(422, 'br07_invalid_category', 'BR-07: category must be one of the platform\'s permitted categories.');
        }

        // BR-60: representative imagery can only be selected under a
        // genuinely active, approved waiver for this tenant+category —
        // checked server-side, not trusted from the request body.
        $wantsRepresentativeMedia = (string) $this->input('media_is_representative_under_waiver') === '1';
        $representativeMediaFlag = false;
        if ($wantsRepresentativeMedia) {
            $hasWaiver = (new \App\Libraries\TenantMediaWaiverService())->isCbsProhibitionWaived($tenantId, $category);
            if (!$hasWaiver) {
                return $this->jsonError(403, 'br60_no_waiver', 'BR-60: this tenant has no active media waiver for this category — representative imagery cannot be used.');
            }
            $representativeMediaFlag = true;
        }

        try {
            $listing = $this->listingModel->createListing([
                'tenant_id' => $tenantId,
                'seller_party_id' => $sellerId,
                'title' => $this->input('title') ?: null,
                'description' => $this->input('description') ?: null,
                'physical_condition' => $this->input('physical_condition'),
                'category' => $category,
                'subcategory' => $this->input('subcategory') ?: null,
                'micro_category' => $this->input('micro_category') ?: null,
                'quantity' => $this->input('quantity'),
                'quantity_basis' => 'unit',
                'make_model' => $this->input('make_model'),
                'yard_location_address' => $this->input('full_address') ?: $this->input('yard_location_address'),
                'yard_location_pin' => $this->input('pincode') ?: $this->input('yard_location_pin'),
                'city' => $this->input('city'),
                'state' => $this->input('state'),
                'media_tier' => $this->input('media_tier') ?: 'certified_by_seller',
                'inspector_party_id' => $inspectorPartyId,
                'surveyor_party_id' => $surveyorPartyId,
                'custodian_party_id' => $custodianPartyId,
                'related_group_id' => $relatedGroupId,
                'related_group_label' => $relatedGroupLabel !== '' ? $relatedGroupLabel : null,
                'shipping_enabled' => $shippingEnabled,
                'shipping_cost_type' => $shippingCostType,
                'shipping_fixed_cost' => $shippingCostType === 'fixed' ? (float) $this->input('shipping_fixed_cost') : null,
                'shipping_variable_rate_per_km' => $shippingCostType === 'variable' ? (float) $this->input('shipping_variable_rate_per_km') : null,
                'media_is_representative_under_waiver' => $representativeMediaFlag,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError(422, 'listing_create_failed', $e->getMessage());
        }

        return $this->apiResponse(['listing' => $listing], null, 201);
    }

    public function show(string $listingId)
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            return $this->jsonError(404, 'not_found', 'Listing not found.');
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

        $viewerId = UserAuthContext::partyId();

        // D-116: BR-42 (seller-discretion acceptance) presumes the seller
        // is the one reviewing offers — every real amount and per-buyer
        // status is only ever returned to the seller, never any other
        // viewer (matches the WS broadcast boundary in D-108).
        if ($saleEvent && $saleEvent['sale_format'] === 'buy_now' && $viewerId === $listing['seller_party_id']) {
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
            $tenderService = new \App\Libraries\TenderService();
            $tenderBidding = new \App\Libraries\TenderBiddingService();
            $tenderReview = new \App\Libraries\TenderReviewService();
            $tenderState = [
                'isEligible' => $viewerId ? $tenderService->isEligible($saleEvent['id'], $viewerId) : false,
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

        // D-105: real per-listing view tracking, feeding the Market
        // Maker's own Lot Reach & Interest dashboard. Fire-and-forget —
        // never blocks or affects the response either way.
        (new \App\Libraries\ListingReachService())->recordView($listingId, $viewerId, $listing['seller_party_id']);

        // BR-32 (D-87/D-88): gates whether the Fee Payer Election's
        // Seller-Pays option is offered on the sale-event attach forms.
        $tenant = $this->tenantModel->find($listing['tenant_id']);
        $authz = new \App\Libraries\AuthorizationService();
        $isTenantAdminForListing = $viewerId
            ? ($authz->isTenantAdminForListing($viewerId, $listingId) || $authz->isSuperAdmin($viewerId))
            : false;

        // D-113: BR-28 cascade — the viewer's own open top-up window on
        // this sale event, if any, drives the "pay your top-up" prompt.
        // Only Easy/Express use the cascade at all (Buy-Now/Tender
        // never populate topup_required_by).
        $myOpenTopup = null;
        $myOpenTopupOwed = null;
        if ($saleEvent && $viewerId && in_array($saleEvent['sale_format'], ['easy', 'express'], true)) {
            $myOpenTopup = (new \App\Models\BidModel())->findOpenTopupForBidder($saleEvent['id'], $viewerId);
            if ($myOpenTopup) {
                $hold = (new \App\Models\EmdHoldModel())->findBySaleEventAndParty($saleEvent['id'], $viewerId);
                $myOpenTopupOwed = \App\Libraries\EmdService::calculateCascadeTopupOwed(
                    $hold ? (float) $hold['amount'] : 0.0, (float) $myOpenTopup['amount']
                );
            }
        }

        // BR-52/PR-30: the viewer's own EMD hold on this sale event, if
        // any (held or forfeited) — drives the "dispute this charge" dev
        // entry point into Chargeback Handling.
        $myEmdHold = ($saleEvent && $viewerId)
            ? (new \App\Models\EmdHoldModel())->findBySaleEventAndParty($saleEvent['id'], $viewerId)
            : null;

        return $this->apiResponse([
            'listing' => $listing, 'saleEvent' => $saleEvent, 'tenant' => $tenant,
            'offers' => $offers, 'expressState' => $expressState, 'tenderState' => $tenderState, 'media' => $media,
            'queuedMediaJobs' => $queuedMediaJobs, 'myOpenTopup' => $myOpenTopup, 'myOpenTopupOwed' => $myOpenTopupOwed,
            'myEmdHold' => $myEmdHold,
            'isOwner' => $viewerId === $listing['seller_party_id'],
            'canFlagCbsViolation' => $isTenantAdminForListing,
            'isTenantAdminForListing' => $isTenantAdminForListing,
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
            return $this->jsonError(422, 'submit_for_approval_failed', $e->getMessage());
        }
        return $this->apiResponse(['listing' => $this->listingModel->find($listingId)]);
    }

    // BR-09: Tenant Admin approval — access enforced by the
    // jwtTenantAdmin route filter, not by this method. If execution
    // reaches here, the caller has already been confirmed as the Tenant
    // Admin for this listing's tenant.
    public function approve(string $listingId)
    {
        $this->lifecycle->approve($listingId, UserAuthContext::partyId());
        return $this->apiResponse(['listing' => $this->listingModel->find($listingId)]);
    }

    public function reject(string $listingId)
    {
        $reasonKey = (string) $this->input('reason_key');
        $detail = $this->input('detail') ?: null;
        try {
            $this->lifecycle->reject($listingId, $reasonKey, $detail, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'reject_failed', $e->getMessage());
        }
        return $this->apiResponse(['listing' => $this->listingModel->find($listingId)]);
    }

    public function editSubmit(string $listingId)
    {
        $partyId = UserAuthContext::partyId();

        $listing = $this->listingModel->find($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return $this->jsonError(403, 'forbidden', 'Only the listing\'s seller may edit it.');
        }

        // BR-07: same closed-list enforcement as creation — an edit can't
        // move a listing into a prohibited category either.
        $newCategory = $this->input('category') ?: $listing['category'];
        if (!in_array($newCategory, ListingLifecycleService::PERMITTED_CATEGORIES, true)) {
            return $this->jsonError(422, 'br07_invalid_category', 'BR-07: category must be one of the platform\'s permitted categories.');
        }

        $newData = [
            'title' => $this->input('title') ?: $listing['title'],
            'physical_condition' => $this->input('physical_condition') ?: $listing['physical_condition'],
            'category' => $newCategory,
            'subcategory' => $this->input('subcategory') ?: $listing['subcategory'],
            'quantity' => $this->input('quantity') ?: $listing['quantity'],
            'quantity_basis' => $listing['quantity_basis'],
            'seller_party_id' => $partyId,
            'yard_location_address' => $this->input('yard_location_address') ?: $listing['yard_location_address'],
            'yard_location_pin' => $this->input('yard_location_pin') ?: $listing['yard_location_pin'],
        ];

        try {
            $result = $this->lifecycle->requestMaterialEdit($listingId, $newData);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'edit_failed', $e->getMessage());
        }

        return $this->apiResponse([
            'listing' => $result['newListing'],
            'message' => 'Listing updated — this is a new listing record (archive-and-recreate per BR-13); any active bids on the old one were withdrawn and EMD released.',
        ]);
    }

    // BR-59/BR-61: CBS violations require manual flagging — automated
    // stock-photo detection is confirmed out of scope (D-59). Available
    // to the Tenant Admin for the listing's own tenant, or Super Admin.
    public function flagCbsViolation(string $listingId)
    {
        $partyId = UserAuthContext::partyId();

        $listing = $this->listingModel->find($listingId);
        if (!$listing) {
            return $this->jsonError(404, 'not_found', 'Listing not found.');
        }

        $authz = new \App\Libraries\AuthorizationService();
        if (!$authz->isTenantAdminFor($partyId, $listing['tenant_id']) && !$authz->isSuperAdmin($partyId)) {
            return $this->jsonError(403, 'forbidden', 'Only this listing\'s Tenant Admin or Super Admin may flag a CBS violation.');
        }

        $result = (new \App\Libraries\StandingReviewService())->recordCbsViolation($listing['seller_party_id'], $partyId, $listingId);

        return $this->apiResponse([
            'offenseNumber' => $result['offenseNumber'],
            'tier' => $result['tier'],
            'message' => "CBS violation logged — offense #{$result['offenseNumber']}, tier: {$result['tier']}.",
        ]);
    }
}
