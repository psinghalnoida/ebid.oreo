<?php

namespace App\Controllers;

use App\Libraries\SellerApplicationService;
use App\Libraries\UserAuthContext;
use App\Models\TenantModel;
use App\Models\SellerApplicationModel;

class SellerApplicationController extends BaseController
{
    private SellerApplicationService $service;
    private TenantModel $tenantModel;
    private SellerApplicationModel $applicationModel;

    public function __construct()
    {
        $this->service = new SellerApplicationService();
        $this->tenantModel = new TenantModel();
        $this->applicationModel = new SellerApplicationModel();
    }

    public function applyStatus(string $tenantId)
    {
        $partyId = UserAuthContext::partyId();
        $tenant = $this->tenantModel->find($tenantId);
        $existing = $this->applicationModel->findForPartyAndTenant($partyId, $tenantId);

        return $this->apiResponse(['tenant' => $tenant, 'existing' => $existing]);
    }

    public function applySubmit(string $tenantId)
    {
        $partyId = UserAuthContext::partyId();

        try {
            $application = $this->service->apply($partyId, $tenantId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'apply_failed', $e->getMessage());
        }

        return $this->apiResponse(['application' => $application, 'message' => 'Application submitted — awaiting Tenant Admin review.'], null, 201);
    }

    // jwtTenantAdmin-gated (resource type 'tenant').
    public function pendingList(string $tenantId)
    {
        $applications = $this->applicationModel->findPendingForTenant($tenantId);
        $tenant = $this->tenantModel->find($tenantId);
        return $this->apiResponse(['applications' => $applications, 'tenant' => $tenant]);
    }

    // jwtTenantAdmin-gated (resource type 'sellerApplication').
    public function approve(string $applicationId)
    {
        try {
            $app = $this->service->approve($applicationId, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'approve_failed', $e->getMessage());
        }
        return $this->apiResponse(['application' => $app]);
    }

    public function reject(string $applicationId)
    {
        $reason = $this->input('reason') ?: 'Not specified';
        try {
            $app = $this->service->reject($applicationId, UserAuthContext::partyId(), $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'reject_failed', $e->getMessage());
        }
        return $this->apiResponse(['application' => $app]);
    }
}
