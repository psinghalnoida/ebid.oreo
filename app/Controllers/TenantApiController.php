<?php

namespace App\Controllers;

use App\Libraries\ApiCredentialService;
use App\Libraries\ApiRequestContext;
use App\Libraries\AuthorizationService;
use App\Libraries\GeminiPreAuditService;
use App\Libraries\KycService;
use App\Libraries\ListingLifecycleService;
use App\Libraries\RatingService;
use App\Libraries\SellerApplicationService;
use App\Libraries\TenantMediaWaiverService;
use App\Models\ListingModel;
use App\Models\PartyModel;
use App\Models\SaleEventModel;
use App\Models\TenantModel;

// BR-62-66/PR-37: the actual Tenant API surface. BR-62's governing
// principle: "The API grants no privilege the portal does not already
// grant, and bypasses none" — every governance check the portal enforces
// (ListingController::createSubmit / SaleEventController::createSubmit)
// is re-enforced here identically; only the entry point (OAuth2 bearer
// token instead of a session, sellerId in the payload instead of a
// logged-in party) differs. BR-63: reads are capped at Tenant-Admin-level
// visibility and hard-scoped to the calling credential's own tenantId —
// a listing/sale event belonging to a different tenant 404s, not 403s,
// so a probing client learns nothing about IDs outside its own tenant.
class TenantApiController extends BaseController
{
    // OAuth2 client-credentials token endpoint. Not behind apiAuth (this
    // IS the authentication step) -- standard form-encoded grant per
    // BR-64's "standard OAuth2 client-credentials flow."
    public function issueToken()
    {
        $grantType = $this->request->getPost('grant_type') ?? $this->request->getJsonVar('grant_type');
        $clientId = $this->request->getPost('client_id') ?? $this->request->getJsonVar('client_id');
        $clientSecret = $this->request->getPost('client_secret') ?? $this->request->getJsonVar('client_secret');

        if ($grantType !== 'client_credentials') {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'unsupported_grant_type', 'error_description' => 'Only grant_type=client_credentials is supported.',
            ]);
        }
        if (!$clientId || !$clientSecret) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'invalid_request', 'error_description' => 'client_id and client_secret are required.',
            ]);
        }

        try {
            $token = (new ApiCredentialService())->authenticate($clientId, $clientSecret);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'invalid_client', 'error_description' => $e->getMessage()]);
        }

        return $this->response->setJSON($token);
    }

    // PR-37 step 2: "Tenant's system pushes a Listing, specifying the
    // acting sellerId. Listing enters PENDING_APPROVAL (BR-13), identical
    // to a portal submission." BR-11's own photo-count gate on
    // submitForApproval() is what actually moves a listing out of
    // INVENTORY on the portal — an API push carries no photos in the same
    // call (no media-push endpoint exists in PR-37's own step list), so
    // this mirrors the portal exactly rather than bypassing that gate:
    // the pushed listing starts at INVENTORY, same as a portal draft, and
    // still requires the existing photo-upload + submit-for-approval path
    // before it genuinely reaches PENDING_APPROVAL. Flagged as a real gap
    // between PR-37's summary wording and BR-11's own requirement, not
    // silently resolved by assumption (see DECISIONS.md).
    // BR-46, extended to the API surface: the portal's version is an
    // interactive "check before you submit" button a seller clicks --
    // that moment doesn't exist for a Tenant pushing Lots through its
    // own backend. Exposed as its own stateless, side-effect-free
    // endpoint (no listing ID, nothing persisted) so a Tenant's own
    // frontend can call it before finalizing a push, giving its own
    // sellers the same AI-assisted moment under the Tenant's own
    // branding. Same tier floor as the push endpoint itself (BR-66) --
    // no separate gate, since a Tenant that can't push Lots at all has
    // nothing to pre-audit.
    public function preAuditListing()
    {
        $tenantId = ApiRequestContext::tenantId();
        $tenant = (new TenantModel())->find($tenantId);
        if (!TenantModel::canPushListings($tenant['subscription_tier'])) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'insufficient_tier', 'error_description' => 'BR-66: listing pre-audit requires TSX Growth or TSX Enterprise.',
            ]);
        }

        $body = $this->request->getJSON(true) ?? [];
        $draft = [
            'category' => $body['category'] ?? null,
            'subcategory' => $body['subcategory'] ?? null,
            'physicalCondition' => $body['physicalCondition'] ?? null,
            'quantity' => $body['quantity'] ?? null,
            'quantityBasis' => $body['quantityBasis'] ?? 'unit',
            'makeModel' => $body['makeModel'] ?? null,
        ];

        try {
            $result = (new GeminiPreAuditService())->evaluate($draft);
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(503)->setJSON(['available' => false, 'message' => $e->getMessage()]);
        }

        return $this->response->setJSON(array_merge(['available' => true], $result));
    }

    public function pushListing()
    {
        $tenantId = ApiRequestContext::tenantId();
        $tenant = (new TenantModel())->find($tenantId);
        if (!TenantModel::canPushListings($tenant['subscription_tier'])) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'insufficient_tier', 'error_description' => 'BR-66: listing push requires TSX Growth or TSX Enterprise.',
            ]);
        }

        $body = $this->request->getJSON(true) ?? [];
        $sellerId = $body['sellerId'] ?? null;
        if (!$sellerId) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'sellerId is required.']);
        }

        $seller = (new PartyModel())->find($sellerId);
        if (!$seller) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'sellerId does not resolve to a known party.']);
        }

        // BR-62: "the platform trusts the Tenant to have already validated
        // that user's seller status" -- but BR-62 also states the API
        // "bypasses none" of the portal's own governance, so every gate
        // the portal enforces on listing creation is re-checked here too.
        if ((new AuthorizationService())->isSuperAdmin($sellerId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'BR-15: the Super Admin may never be attributed as a seller.']);
        }
        try {
            (new KycService())->requireVerifiedKyc($sellerId, 'creating a Listing via the Tenant API');
        } catch (\RuntimeException $e) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => $e->getMessage()]);
        }
        if ((new RatingService())->isDelisted($sellerId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'BR-38: this seller has been delisted from selling on eBid Hub due to a confirmed fraud finding.']);
        }
        if (!(new SellerApplicationService())->isApprovedSeller($sellerId, $tenantId)) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'BR-09: sellerId is not an approved Seller on this TSX.']);
        }

        $category = $body['category'] ?? null;
        if (!in_array($category, ListingLifecycleService::PERMITTED_CATEGORIES, true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'BR-07: category must be one of the platform\'s permitted categories.']);
        }

        $shippingEnabled = (bool) ($body['shippingEnabled'] ?? false);
        $shippingCostType = $shippingEnabled ? ($body['shippingCostType'] ?? null) : null;
        if ($shippingEnabled && !in_array($shippingCostType, ['fixed', 'variable'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'BR-24: shippingCostType must be "fixed" or "variable" when shippingEnabled is true.']);
        }

        $representativeMediaFlag = false;
        if (!empty($body['mediaIsRepresentativeUnderWaiver'])) {
            if (!(new TenantMediaWaiverService())->isCbsProhibitionWaived($tenantId, $category)) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'BR-60: this TSX has no active media waiver for this category.']);
            }
            $representativeMediaFlag = true;
        }

        try {
            $listing = (new ListingModel())->createListing([
                'tenant_id' => $tenantId,
                'seller_party_id' => $sellerId,
                'physical_condition' => $body['physicalCondition'] ?? null,
                'category' => $category,
                'subcategory' => $body['subcategory'] ?? null,
                'quantity' => $body['quantity'] ?? null,
                'quantity_basis' => $body['quantityBasis'] ?? 'unit',
                'make_model' => $body['makeModel'] ?? null,
                'yard_location_address' => $body['yardLocationAddress'] ?? null,
                'yard_location_pin' => $body['yardLocationPin'] ?? null,
                'media_tier' => $body['mediaTier'] ?? 'certified_by_seller',
                'inspector_party_id' => $body['inspectorPartyId'] ?? null,
                'surveyor_party_id' => $body['surveyorPartyId'] ?? null,
                'custodian_party_id' => $body['custodianPartyId'] ?? null,
                'shipping_enabled' => $shippingEnabled,
                'shipping_cost_type' => $shippingCostType,
                'shipping_fixed_cost' => $shippingCostType === 'fixed' ? (float) ($body['shippingFixedCost'] ?? 0) : null,
                'shipping_variable_rate_per_km' => $shippingCostType === 'variable' ? (float) ($body['shippingVariableRatePerKm'] ?? 0) : null,
                'media_is_representative_under_waiver' => $representativeMediaFlag,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => $e->getMessage()]);
        }

        return $this->response->setStatusCode(201)->setJSON($this->listingPayload($listing));
    }

    public function getListing(string $listingId)
    {
        $listing = (new ListingModel())->findActiveById($listingId);
        // BR-63: 404, not 403, for a listing outside the calling
        // credential's own tenant -- a probing client learns nothing
        // about the existence of another tenant's IDs.
        if (!$listing || $listing['tenant_id'] !== ApiRequestContext::tenantId()) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'not_found']);
        }
        return $this->response->setJSON($this->listingPayload($listing));
    }

    // PR-37 step 5. Tender excluded entirely (Company Shop-managed only,
    // BR-12) -- matches PR-37's own explicit exclusion.
    public function pushSaleEvent(string $listingId)
    {
        $tenantId = ApiRequestContext::tenantId();
        $tenant = (new TenantModel())->find($tenantId);
        if (!TenantModel::canPushSaleEvents($tenant['subscription_tier'])) {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'insufficient_tier', 'error_description' => 'BR-66: Sale System push requires TSX Enterprise.',
            ]);
        }

        $listing = (new ListingModel())->findActiveById($listingId);
        if (!$listing || $listing['tenant_id'] !== $tenantId) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'not_found']);
        }
        if ($listing['status'] !== 'upcoming') {
            return $this->response->setStatusCode(409)->setJSON(['error' => 'invalid_state', 'error_description' => 'Listing must be approved (upcoming) before attaching a Sale System.']);
        }

        $body = $this->request->getJSON(true) ?? [];
        $format = $body['saleFormat'] ?? null;
        if (!in_array($format, ['easy', 'buy_now', 'express'], true)) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'saleFormat must be one of: easy, buy_now, express. Tender is Company Shop-managed only (BR-12/PR-37).']);
        }
        // BR-62: sellerId in the payload must match this listing's own
        // seller -- the Tenant attests to the acting party on every call.
        if (($body['sellerId'] ?? null) !== $listing['seller_party_id']) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'sellerId must match this listing\'s own seller_party_id.']);
        }

        $ernPrefix = match ($format) { 'buy_now' => 'BN-', 'express' => 'EX-', default => 'EH-' };
        $data = [
            'listing_id' => $listingId, 'tenant_id' => $tenantId,
            'ern' => $ernPrefix . strtoupper(substr($listingId, 0, 8)) . '-API',
            'sale_format' => $format,
        ];

        if ($format === 'buy_now') {
            $data['expected_value'] = $body['expectedValue'] ?? null;
        } else {
            $data['reserve_value'] = $body['reserveValue'] ?? null;
            $data['result_mode'] = 'instant_close';
        }

        if ($format === 'easy') {
            $startAt = $body['scheduledStartAt'] ?? null;
            $endAt = $body['scheduledEndAt'] ?? null;
            if (!$startAt || !$endAt || strtotime((string) $endAt) <= strtotime((string) $startAt)) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'Easy Auction requires scheduledStartAt and scheduledEndAt, with end after start.']);
            }
            $data['scheduled_start_at'] = date('Y-m-d H:i:s', strtotime($startAt));
            $data['scheduled_end_at'] = date('Y-m-d H:i:s', strtotime($endAt));
            $incrementPercent = (float) ($body['incrementPercent'] ?? 2);
            if ($incrementPercent < 2 || $incrementPercent > 5) {
                return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => 'incrementPercent must be between 2 and 5.']);
            }
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * ($incrementPercent / 100), 2);
        }
        if ($format === 'express') {
            $data['bid_increment_amount'] = round(((float) $data['reserve_value']) * 0.02, 2);
        }

        // BR-38: same seller-ceiling gate the portal enforces.
        $sellerValue = $data['reserve_value'] ?? $data['expected_value'] ?? null;
        if ($sellerValue !== null) {
            $ceiling = (new RatingService())->getTransactionCeiling($listing['seller_party_id'], 'seller_star_rating', $tenant);
            if ($ceiling !== null && (float) $sellerValue > $ceiling) {
                return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => "BR-38: this seller is currently restricted to listings valued up to {$ceiling}."]);
            }
        }

        // BR-31/32 (D-87/D-88): Fee Payer Election, same tier gate as the portal.
        $feePayer = ($body['feePayer'] ?? 'buyer_pays') === 'seller_pays' ? 'seller_pays' : 'buyer_pays';
        if ($feePayer === 'seller_pays' && $tenant['subscription_tier'] === 'coco_starter') {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'forbidden', 'error_description' => 'BR-32: Seller-Pays is not available on a CoCo Starter TSX.']);
        }
        $data['fee_payer'] = $feePayer;

        try {
            $saleEvent = (new SaleEventModel())->createSaleEvent($data);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'invalid_request', 'error_description' => $e->getMessage()]);
        }

        // BR-13: listing moves to active once a sale system is attached — identical to the portal.
        (new ListingModel())->transitionStatus($listingId, 'active');

        return $this->response->setStatusCode(201)->setJSON($this->saleEventPayload($saleEvent));
    }

    public function getSaleEvent(string $saleEventId)
    {
        $saleEvent = (new SaleEventModel())->find($saleEventId);
        if (!$saleEvent || $saleEvent['tenant_id'] !== ApiRequestContext::tenantId()) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'not_found']);
        }
        return $this->response->setJSON($this->saleEventPayload($saleEvent));
    }

    // BR-63: exactly the fields a Tenant Admin already sees on the portal
    // for their own listing -- no audit-trail or cross-tenant fields.
    private function listingPayload(array $listing): array
    {
        return [
            'id' => $listing['id'], 'tenantId' => $listing['tenant_id'], 'sellerId' => $listing['seller_party_id'],
            'status' => $listing['status'], 'category' => $listing['category'], 'subcategory' => $listing['subcategory'],
            'physicalCondition' => $listing['physical_condition'], 'quantity' => $listing['quantity'],
            'makeModel' => $listing['make_model'], 'yardLocationAddress' => $listing['yard_location_address'],
            'yardLocationPin' => $listing['yard_location_pin'], 'rejectionReason' => $listing['rejection_reason'],
            'supersededByListingId' => $listing['superseded_by_listing_id'],
        ];
    }

    private function saleEventPayload(array $saleEvent): array
    {
        return [
            'id' => $saleEvent['id'], 'listingId' => $saleEvent['listing_id'], 'tenantId' => $saleEvent['tenant_id'],
            'ern' => $saleEvent['ern'], 'saleFormat' => $saleEvent['sale_format'], 'status' => $saleEvent['status'],
            'feePayer' => $saleEvent['fee_payer'], 'currentPrice' => $saleEvent['current_price'],
            'reserveValue' => $saleEvent['reserve_value'], 'expectedValue' => $saleEvent['expected_value'],
        ];
    }
}
