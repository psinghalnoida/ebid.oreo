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
        return view('admin/tenant_create', ['title' => 'Whitelist a Tenant — AdwitiX']);
    }

    public function createSubmit()
    {
        $name = $this->request->getPost('name');
        $tenantClass = $this->request->getPost('tenant_class') ?: 'general';
        $subdomain = $this->request->getPost('subdomain');
        $subscriptionTier = $this->request->getPost('subscription_tier') ?: 'coco_starter';
        if (!in_array($subscriptionTier, TenantModel::SUBSCRIPTION_TIERS, true)) {
            return redirect()->to('/admin/tenants/create')->with('error', 'Invalid subscription tier.');
        }
        // BR-06: "a dedicated subdomain ... or custom domain" — optional,
        // set once at whitelisting time like subdomain itself (see
        // tenant_view.php's own note on why domain fields aren't casually
        // edited later — a routing-affecting decision, not a quick tweak).
        $customDomain = trim((string) $this->request->getPost('custom_domain')) ?: null;

        if (!$name || !$subdomain) {
            return redirect()->to('/admin/tenants/create')->with('error', 'Name and subdomain are required.');
        }

        try {
            $tenant = $this->tenantModel->createTenant([
                'name' => $name, 'tenant_class' => $tenantClass,
                'subdomain' => $subdomain, 'custom_domain' => $customDomain, 'subscription_tier' => $subscriptionTier,
            ]);
        } catch (\Throwable $e) {
            return redirect()->to('/admin/tenants/create')->with('error', 'Could not create tenant — subdomain or custom domain may already be in use.');
        }

        return redirect()->to('/admin')->with('error', "Tenant \"{$tenant['name']}\" whitelisted successfully.");
    }

    // Was missing entirely — the dashboard embedded a tenant table but
    // there was no dedicated, searchable list page.
    public function list()
    {
        $q = trim((string) $this->request->getGet('q'));
        $builder = $this->tenantModel->orderBy('name', 'ASC');
        if ($q !== '') {
            $builder = $builder->groupStart()->like('name', $q)->orLike('subdomain', $q)->groupEnd();
        }
        return view('admin/tenants_list', ['title' => 'Tenants — AdwitiX', 'tenants' => $builder->findAll(), 'q' => $q]);
    }

    // Was missing entirely — Super Admin could only create tenants, not
    // view or correct one afterward.
    public function view(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return view('admin/tenant_view', ['title' => 'Tenant — AdwitiX', 'tenant' => $tenant]);
    }

    // BR-06: tenant branding (logo + primary color) — the columns have
    // existed since Phase 0 but nothing ever wrote to them.
    public function editSubmit(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $postedColor = $this->request->getPost('branding_primary_color');
        if ($postedColor && !preg_match('/^#[0-9a-fA-F]{6}$/', $postedColor)) {
            return redirect()->to("/admin/tenants/{$tenantId}")->with('error', 'Brand color must be a 6-digit hex code, e.g. #0F6E4E.');
        }

        $subscriptionTier = $this->request->getPost('subscription_tier') ?: $tenant['subscription_tier'];
        if (!in_array($subscriptionTier, TenantModel::SUBSCRIPTION_TIERS, true)) {
            return redirect()->to("/admin/tenants/{$tenantId}")->with('error', 'Invalid subscription tier.');
        }

        $update = [
            'name' => $this->request->getPost('name') ?: $tenant['name'],
            'subscription_tier' => $subscriptionTier,
            'branding_primary_color' => $postedColor ?: $tenant['branding_primary_color'],
            'terms_url' => $this->request->getPost('terms_url') ?: $tenant['terms_url'],
        ];

        $logo = $this->request->getFile('branding_logo');
        if ($logo && $logo->isValid() && !$logo->hasMoved()) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
            if (!in_array($logo->getMimeType(), $allowed, true)) {
                return redirect()->to("/admin/tenants/{$tenantId}")->with('error', 'Logo must be JPEG, PNG, WebP, or SVG.');
            }
            $uploadDir = WRITEPATH . '../public/uploads/tenants/' . $tenantId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = 'logo_' . time() . '.' . $logo->getExtension();
            $logo->move($uploadDir, $filename);
            $update['branding_logo_url'] = '/uploads/tenants/' . $tenantId . '/' . $filename;
        }

        $this->tenantModel->update($tenantId, $update);
        return redirect()->to("/admin/tenants/{$tenantId}")->with('error', 'Tenant updated.');
    }

    // Was missing entirely — a seller had no way to discover which
    // tenants exist without already knowing a tenant ID.
    public function directory()
    {
        $tenants = $this->tenantModel->orderBy('name', 'ASC')->findAll();
        return view('tenants_directory', ['title' => 'Browse Tenants — AdwitiX', 'tenants' => $tenants]);
    }
}
