<?php

namespace App\Controllers;

use App\Libraries\TenantMediaWaiverService;
use App\Libraries\UserAuthContext;
use App\Models\TenantModel;

class TenantMediaWaiverController extends BaseController
{
    // jwtAuth-gated; the Tenant Admin check is inline since it's scoped
    // to the URL's own tenantId, same shape as ListingController's own
    // inline Tenant Admin checks.
    public function requestSubmit(string $tenantId)
    {
        $partyId = UserAuthContext::partyId();

        if (!(new \App\Libraries\AuthorizationService())->isTenantAdminFor($partyId, $tenantId)) {
            return $this->jsonError(403, 'forbidden', 'Only this tenant\'s Tenant Admin may request a media waiver.');
        }

        try {
            $waiver = (new TenantMediaWaiverService())->requestWaiver(
                $tenantId, $partyId,
                $this->input('category'), $this->input('business_justification')
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'request_failed', $e->getMessage());
        }

        return $this->apiResponse(['waiver' => $waiver, 'message' => 'Waiver request submitted for SaaS Admin review.'], null, 201);
    }

    // jwtSuperAdmin-gated below.
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

        return $this->apiResponse(['pending' => $pending, 'active' => $active]);
    }

    public function decide(string $waiverId)
    {
        $superAdminId = UserAuthContext::partyId();
        $approve = $this->input('decision') === 'approve';
        $rationale = $this->input('rationale');

        try {
            $waiver = (new TenantMediaWaiverService())->decide($waiverId, $superAdminId, $approve, $rationale);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'decide_failed', $e->getMessage());
        }

        return $this->apiResponse(['waiver' => $waiver]);
    }

    public function revoke(string $waiverId)
    {
        $superAdminId = UserAuthContext::partyId();
        $reason = $this->input('reason');

        try {
            $waiver = (new TenantMediaWaiverService())->revoke($waiverId, $superAdminId, $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'revoke_failed', $e->getMessage());
        }

        return $this->apiResponse(['waiver' => $waiver]);
    }
}
