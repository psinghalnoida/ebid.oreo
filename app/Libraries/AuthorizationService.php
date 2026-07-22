<?php

namespace App\Libraries;

use App\Models\PartyRoleModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;

class AuthorizationService
{
    private PartyRoleModel $roleModel;
    private ListingModel $listingModel;
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->roleModel = new PartyRoleModel();
        $this->listingModel = new ListingModel();
        $this->saleEventModel = new SaleEventModel();
    }

    public function isTenantAdminFor(string $partyId, string $tenantId): bool
    {
        return $this->roleModel->hasActiveRole($partyId, 'tenant_admin', $tenantId);
    }

    // BR-09: Tenant Admin authority is scoped to a listing via that
    // listing's own tenant_id — resolves it and checks in one call.
    public function isTenantAdminForListing(string $partyId, string $listingId): bool
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            return false;
        }
        return $this->isTenantAdminFor($partyId, $listing['tenant_id']);
    }

    // BR-21: the listing's assigned inspector may not bid/offer/pledge on
    // that same listing. BR-22: a Tenant Admin may not bid/offer/pledge
    // on any listing belonging to their own tenant's storefront. Both
    // checked together since every "commit to buying" entry point
    // (BiddingService, OfferService, ExpressAuctionService) needs both.
    public function hasConflictOfInterest(string $partyId, string $listingId): ?string
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            return null;
        }
        if ($listing['inspector_party_id'] === $partyId) {
            return 'BR-21: the assigned inspector for a listing may not bid, offer, or pledge on it.';
        }
        if ($this->isTenantAdminFor($partyId, $listing['tenant_id'])) {
            return 'BR-22: a Tenant Admin may not bid, offer, or pledge on a listing belonging to their own tenant.';
        }
        return null;
    }

    // ⚠️ MINIMAL STAND-IN: this checks role membership only — it is NOT
    // BR-04's separate Auth0/TOTP Super Admin login path, which remains
    // deferred (Tier 3, D-23). This exists only to unblock BR-40's real
    // requirement that a Super Admin rule on buyer-side disputes and
    // appeals — Dispute Resolution couldn't be built at all without some
    // form of this authorization existing first. Provisioned via
    // `php spark grant:super-admin`, same pattern as tenant_admin.
    public function isSuperAdmin(string $partyId): bool
    {
        $roleModel = new \App\Models\PartyRoleModel();
        return $roleModel->hasActiveRole($partyId, 'super_admin', null);
    }

    public function isTenantAdminForSaleEvent(string $partyId, string $saleEventId): bool
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            return false;
        }
        return $this->isTenantAdminFor($partyId, $saleEvent['tenant_id']);
    }

    public function isTenantAdminForSettlement(string $partyId, string $settlementId): bool
    {
        $settlementModel = new \App\Models\SettlementModel();
        $settlement = $settlementModel->find($settlementId);
        if (!$settlement) {
            return false;
        }
        return $this->isTenantAdminForSaleEvent($partyId, $settlement['sale_event_id']);
    }

    public function isTenantAdminForSellerApplication(string $partyId, string $applicationId): bool
    {
        $applicationModel = new \App\Models\SellerApplicationModel();
        $app = $applicationModel->find($applicationId);
        if (!$app) {
            return false;
        }
        return $this->isTenantAdminFor($partyId, $app['tenant_id']);
    }
}
