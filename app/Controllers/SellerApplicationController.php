<?php

namespace App\Controllers;

use App\Libraries\SellerApplicationService;
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

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function applyForm(string $tenantId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $tenant = $this->tenantModel->find($tenantId);
        $existing = $this->applicationModel->findForPartyAndTenant($partyId, $tenantId);

        return view('seller/apply', ['title' => 'Apply to Sell — eBid Hub', 'tenant' => $tenant, 'existing' => $existing]);
    }

    public function applySubmit(string $tenantId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        try {
            $this->service->apply($partyId, $tenantId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/tenants/{$tenantId}/apply-to-sell")->with('error', $e->getMessage());
        }

        return redirect()->to("/tenants/{$tenantId}/apply-to-sell")->with('error', 'Application submitted — awaiting Tenant Admin review.');
    }

    public function pendingList(string $tenantId)
    {
        $applications = $this->applicationModel->findPendingForTenant($tenantId);
        $tenant = $this->tenantModel->find($tenantId);
        return view('seller/pending', ['title' => 'Pending Seller Applications — eBid Hub', 'applications' => $applications, 'tenant' => $tenant]);
    }

    public function approve(string $applicationId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $app = $this->service->approve($applicationId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->to("/tenants/{$app['tenant_id']}/pending-sellers");
    }

    public function reject(string $applicationId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $reason = $this->request->getPost('reason') ?: 'Not specified';
        try {
            $app = $this->service->reject($applicationId, $partyId, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->to("/tenants/{$app['tenant_id']}/pending-sellers");
    }
}
