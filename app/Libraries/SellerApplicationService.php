<?php

namespace App\Libraries;

use App\Models\SellerApplicationModel;
use App\Models\PartyRoleModel;
use App\Models\ListingModel;

class SellerApplicationService
{
    private SellerApplicationModel $applicationModel;
    private PartyRoleModel $roleModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->applicationModel = new SellerApplicationModel();
        $this->roleModel = new PartyRoleModel();
        $this->listingModel = new ListingModel();
    }

    public function apply(string $partyId, string $tenantId): array
    {
        $existing = $this->applicationModel->findForPartyAndTenant($partyId, $tenantId);
        if ($existing) {
            throw new \RuntimeException("An application for this tenant already exists (status: {$existing['status']}).");
        }
        return $this->applicationModel->createApplication($partyId, $tenantId);
    }

    public function approve(string $applicationId, string $tenantAdminId): array
    {
        $app = $this->requireApplication($applicationId);
        $this->requireTenantAdmin($tenantAdminId, $app['tenant_id']);
        if ($app['status'] !== 'pending') {
            throw new \RuntimeException('This application has already been decided.');
        }

        $this->applicationModel->update($applicationId, [
            'status' => 'approved', 'decided_by_party_id' => $tenantAdminId, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        $this->roleModel->grantRole($app['party_id'], 'seller', $app['tenant_id']);
        return $this->applicationModel->find($applicationId);
    }

    public function reject(string $applicationId, string $tenantAdminId, string $reason): array
    {
        $app = $this->requireApplication($applicationId);
        $this->requireTenantAdmin($tenantAdminId, $app['tenant_id']);
        if ($app['status'] !== 'pending') {
            throw new \RuntimeException('This application has already been decided.');
        }

        $this->applicationModel->update($applicationId, [
            'status' => 'rejected', 'rejection_reason' => $reason,
            'decided_by_party_id' => $tenantAdminId, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->applicationModel->find($applicationId);
    }

    public function isApprovedSeller(string $partyId, string $tenantId): bool
    {
        return $this->roleModel->hasActiveRole($partyId, 'seller', $tenantId);
    }

    // BR-09: suspension cascade — revoking seller status instantly
    // suspends every active listing that seller has on THIS tenant only.
    public function suspendSeller(string $partyId, string $tenantId, string $tenantAdminId, string $reason): array
    {
        $this->requireTenantAdmin($tenantAdminId, $tenantId);

        $activeRole = $this->applicationModel->where('party_id', $partyId)->where('tenant_id', $tenantId)->where('status', 'approved')->first();
        if (!$activeRole) {
            throw new \RuntimeException('This party does not hold an approved seller status on this tenant.');
        }

        $db = \Config\Database::connect();
        $db->table('party_role')
            ->where('party_id', $partyId)->where('tenant_id', $tenantId)->where('role', 'seller')->where('revoked_at', null)
            ->update(['revoked_at' => date('Y-m-d H:i:s')]);

        $suspended = $db->table('listing')
            ->where('seller_party_id', $partyId)->where('tenant_id', $tenantId)
            ->whereIn('status', ['inventory', 'pending_approval', 'upcoming', 'active'])
            ->get()->getResultArray();

        foreach ($suspended as $listing) {
            $this->listingModel->update($listing['id'], [
                'status' => 'suspended', 'rejection_reason' => $reason, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['revokedFrom' => $partyId, 'listingsSuspended' => count($suspended)];
    }

    private function requireApplication(string $applicationId): array
    {
        $app = $this->applicationModel->find($applicationId);
        if (!$app) {
            throw new \RuntimeException('Application not found');
        }
        return $app;
    }

    private function requireTenantAdmin(string $partyId, string $tenantId): void
    {
        $auth = new AuthorizationService();
        if (!$auth->isTenantAdminFor($partyId, $tenantId)) {
            throw new \RuntimeException('BR-09: only the Tenant Admin for this tenant may act on seller applications.');
        }
    }
}
