<?php

namespace App\Controllers;

use App\Models\TenantModel;

class TenantController extends BaseController
{
    private TenantModel $tenantModel;

    public function __construct()
    {
        $this->tenantModel = new TenantModel();
    }

    // BR-06: tenant creation IS the whitelisting act — a tenant only
    // exists once a Super Admin has whitelisted it. Gated behind the
    // real TOTP-verified Super Admin session (superAdmin filter).
    public function createForm()
    {
        return view('admin/tenant_create', ['title' => 'Whitelist a Tenant — eBid Hub']);
    }

    public function createSubmit()
    {
        $name = $this->request->getPost('name');
        $tenantClass = $this->request->getPost('tenant_class') ?: 'general';
        $subdomain = $this->request->getPost('subdomain');
        $buyerFeePercent = $this->request->getPost('buyer_fee_percent') ?: 5.00;

        if (!$name || !$subdomain) {
            return redirect()->to('/admin/tenants/create')->with('error', 'Name and subdomain are required.');
        }

        try {
            $tenant = $this->tenantModel->createTenant([
                'name' => $name, 'tenant_class' => $tenantClass,
                'subdomain' => $subdomain, 'buyer_fee_percent' => $buyerFeePercent,
            ]);
        } catch (\Throwable $e) {
            return redirect()->to('/admin/tenants/create')->with('error', 'Could not create tenant — subdomain may already be in use.');
        }

        return redirect()->to('/admin')->with('error', "Tenant \"{$tenant['name']}\" whitelisted successfully.");
    }

    // Was missing entirely — Super Admin could only create tenants, not
    // view or correct one afterward.
    public function view(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('admin/tenant_view', ['title' => 'Tenant — eBid Hub', 'tenant' => $tenant]);
    }

    public function editSubmit(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->tenantModel->update($tenantId, [
            'name' => $this->request->getPost('name') ?: $tenant['name'],
            'buyer_fee_percent' => $this->request->getPost('buyer_fee_percent') ?: $tenant['buyer_fee_percent'],
        ]);
        return redirect()->to("/admin/tenants/{$tenantId}")->with('error', 'Tenant updated.');
    }

    // Was missing entirely — a seller had no way to discover which
    // tenants exist without already knowing a tenant ID.
    public function directory()
    {
        $tenants = $this->tenantModel->orderBy('name', 'ASC')->findAll();
        return view('tenants_directory', ['title' => 'Browse Tenants — eBid Hub', 'tenants' => $tenants]);
    }
}
