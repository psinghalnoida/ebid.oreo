<?php

namespace App\Controllers;

use App\Libraries\TenantMediaWaiverService;
use App\Models\TenantMediaWaiverModel;
use App\Models\TenantModel;

class TenantMediaWaiverController extends BaseController
{
    public function requestForm(string $tenantId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (!(new \App\Libraries\AuthorizationService())->isTenantAdminFor($partyId, $tenantId)) {
            return redirect()->to('/')->with('error', 'Only this tenant\'s Tenant Admin may request a media waiver.');
        }

        return view('tenant_admin/media_waiver_request', ['title' => 'Request Media Waiver — AdwitiX', 'tenant' => $tenant]);
    }

    public function requestSubmit(string $tenantId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        if (!(new \App\Libraries\AuthorizationService())->isTenantAdminFor($partyId, $tenantId)) {
            return redirect()->to('/')->with('error', 'Only this tenant\'s Tenant Admin may request a media waiver.');
        }

        try {
            (new TenantMediaWaiverService())->requestWaiver(
                $tenantId, $partyId,
                $this->request->getPost('category'), $this->request->getPost('business_justification')
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to("/tenants/{$tenantId}/dashboard")->with('error', 'Waiver request submitted for SaaS Admin review.');
    }

    public function pendingList()
    {
        $db = \Config\Database::connect();
        $pending = $db->table('tenant_media_waiver tmw')
            ->select('tmw.*, t.name as tenant_name')
            ->join('tenant t', 't.id = tmw.tenant_id')
            ->where('tmw.status', 'pending')
            ->orderBy('tmw.created_at', 'ASC')
            ->get()->getResultArray();

        $active = $db->table('tenant_media_waiver tmw')
            ->select('tmw.*, t.name as tenant_name')
            ->join('tenant t', 't.id = tmw.tenant_id')
            ->where('tmw.status', 'approved')
            ->orderBy('tmw.expires_at', 'ASC')
            ->get()->getResultArray();

        return view('admin/media_waivers', ['title' => 'Tenant Media Waivers — AdwitiX', 'pending' => $pending, 'active' => $active]);
    }

    public function decide(string $waiverId)
    {
        $superAdminId = session()->get('logged_in_party_id');
        $approve = $this->request->getPost('decision') === 'approve';
        $rationale = $this->request->getPost('rationale');

        try {
            (new TenantMediaWaiverService())->decide($waiverId, $superAdminId, $approve, $rationale);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/media-waivers')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/media-waivers');
    }

    public function revoke(string $waiverId)
    {
        $superAdminId = session()->get('logged_in_party_id');
        $reason = $this->request->getPost('reason');

        try {
            (new TenantMediaWaiverService())->revoke($waiverId, $superAdminId, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/media-waivers')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/media-waivers');
    }
}
