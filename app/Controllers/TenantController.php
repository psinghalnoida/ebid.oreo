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
    // exists once a Super Admin has whitelisted it. jwtSuperAdmin-gated.
    public function createSubmit()
    {
        $name = $this->input('name');
        $tenantClass = $this->input('tenant_class') ?: 'general';
        $subdomain = $this->input('subdomain');
        $subscriptionTier = $this->input('subscription_tier') ?: 'coco_starter';
        if (!in_array($subscriptionTier, TenantModel::SUBSCRIPTION_TIERS, true)) {
            return $this->jsonError(422, 'invalid_tier', 'Invalid subscription tier.');
        }
        // BR-06: "a dedicated subdomain ... or custom domain" — optional,
        // set once at whitelisting time like subdomain itself.
        $customDomain = trim((string) $this->input('custom_domain')) ?: null;

        if (!$name || !$subdomain) {
            return $this->jsonError(422, 'missing_fields', 'Name and subdomain are required.');
        }

        try {
            $tenant = $this->tenantModel->createTenant([
                'name' => $name, 'tenant_class' => $tenantClass,
                'subdomain' => $subdomain, 'custom_domain' => $customDomain, 'subscription_tier' => $subscriptionTier,
            ]);
        } catch (\Throwable $e) {
            return $this->jsonError(422, 'create_failed', 'Could not create tenant — subdomain or custom domain may already be in use.');
        }

        return $this->apiResponse(['tenant' => $tenant, 'message' => "Tenant \"{$tenant['name']}\" whitelisted successfully."], null, 201);
    }

    // jwtSuperAdmin-gated.
    public function list()
    {
        $q = trim((string) $this->request->getGet('q'));
        $builder = $this->tenantModel->orderBy('name', 'ASC');
        if ($q !== '') {
            $builder = $builder->groupStart()->like('name', $q)->orLike('subdomain', $q)->groupEnd();
        }
        return $this->apiResponse(['tenants' => $builder->findAll(), 'q' => $q]);
    }

    // jwtSuperAdmin-gated.
    public function view(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }
        return $this->apiResponse(['tenant' => $tenant]);
    }

    // BR-06: tenant branding (logo + primary color). jwtSuperAdmin-gated.
    // Multipart/form-data (branding_logo upload), same reasoning as
    // MediaController::upload.
    public function editSubmit(string $tenantId)
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        $postedColor = $this->request->getPost('branding_primary_color');
        if ($postedColor && !preg_match('/^#[0-9a-fA-F]{6}$/', $postedColor)) {
            return $this->jsonError(422, 'invalid_color', 'Brand color must be a 6-digit hex code, e.g. #0F6E4E.');
        }

        $subscriptionTier = $this->request->getPost('subscription_tier') ?: $tenant['subscription_tier'];
        if (!in_array($subscriptionTier, TenantModel::SUBSCRIPTION_TIERS, true)) {
            return $this->jsonError(422, 'invalid_tier', 'Invalid subscription tier.');
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
                return $this->jsonError(422, 'invalid_logo_type', 'Logo must be JPEG, PNG, WebP, or SVG.');
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
        return $this->apiResponse(['tenant' => $this->tenantModel->find($tenantId), 'message' => 'Tenant updated.']);
    }

    // Public — a seller browsing which tenants exist without already
    // knowing a tenant ID.
    public function directory()
    {
        $tenants = $this->tenantModel->orderBy('name', 'ASC')->findAll();
        return $this->apiResponse(['tenants' => $tenants]);
    }
}
