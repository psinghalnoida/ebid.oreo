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

    public function isTenantAdminForSaleEvent(string $partyId, string $saleEventId): bool
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            return false;
        }
        return $this->isTenantAdminFor($partyId, $saleEvent['tenant_id']);
    }
}
