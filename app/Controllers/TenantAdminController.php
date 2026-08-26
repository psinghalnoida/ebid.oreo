<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\SellerApplicationModel;

// All routes jwtTenantAdmin-gated (resource type 'tenant').
class TenantAdminController extends BaseController
{
    public function dashboard(string $tenantId)
    {
        $tenantModel = new TenantModel();
        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        $db = \Config\Database::connect();

        $pendingListings = $db->table('listing')
            ->where('tenant_id', $tenantId)->where('status', 'pending_approval')
            ->get()->getResultArray();

        $pendingSaleEvents = $db->table('sale_event')
            ->where('tenant_id', $tenantId)->where('status', 'pending_approval')
            ->get()->getResultArray();

        $sellerApplicationModel = new SellerApplicationModel();
        $pendingSellers = $sellerApplicationModel->findPendingForTenant($tenantId);

        $saleEventIds = array_column(
            $db->table('sale_event')->where('tenant_id', $tenantId)->get()->getResultArray(), 'id'
        );
        $openDisputes = empty($saleEventIds) ? [] : $db->table('dispute')
            ->whereIn('sale_event_id', $saleEventIds)
            ->whereIn('status', ['filed', 'evidence_window'])
            ->get()->getResultArray();

        $stalledSettlements = empty($saleEventIds) ? [] : $db->table('settlement')
            ->whereIn('sale_event_id', $saleEventIds)
            ->where('status', 'stalled')
            ->get()->getResultArray();

        // BR-49: surfaced to the Tenant Admin, no manual trigger.
        $highValueDisposals = $db->table('high_value_disposal_record')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'tenant' => $tenant,
            'pendingListings' => $pendingListings,
            'pendingSaleEvents' => $pendingSaleEvents,
            'pendingSellers' => $pendingSellers,
            'openDisputes' => $openDisputes,
            'stalledSettlements' => $stalledSettlements,
            'highValueDisposals' => $highValueDisposals,
        ]);
    }

    // PR-09 step 7 / D-118: the consolidated "Lot & Trading Session
    // Approval" queue — pending listings with real media context, plus
    // pending Sale Event approvals.
    public function verification(string $tenantId)
    {
        $tenantModel = new TenantModel();
        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        $db = \Config\Database::connect();
        $pending = $db->table('listing')
            ->where('tenant_id', $tenantId)->where('status', 'pending_approval')
            ->orderBy('created_at', 'ASC')
            ->get()->getResultArray();

        foreach ($pending as &$listing) {
            $primary = $db->table('listing_media')
                ->where('listing_id', $listing['id'])->where('is_primary', true)
                ->get()->getRowArray();
            $listing['primaryPhotoPath'] = $primary['file_path'] ?? null;
            $listing['photoCount'] = $db->table('listing_media')->where('listing_id', $listing['id'])->where('media_type', 'photo')->countAllResults();
            $listing['videoCount'] = $db->table('listing_media')->where('listing_id', $listing['id'])->where('media_type', 'video')->countAllResults();
            $listing['documentCount'] = $db->table('listing_media')->where('listing_id', $listing['id'])->where('media_type', 'document')->countAllResults();
            $listing['queuedJobCount'] = $db->table('media_upload_job')
                ->where('listing_id', $listing['id'])->whereIn('status', ['pending', 'processing'])->countAllResults();
        }
        unset($listing);

        $pendingSaleEvents = $db->table('sale_event se')
            ->select('se.id, se.ern, se.sale_format, se.reserve_value, se.expected_value, se.listing_id, l.category, l.subcategory')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('se.tenant_id', $tenantId)->where('se.status', 'pending_approval')
            ->orderBy('se.created_at', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON(['tenant' => $tenant, 'pending' => $pending, 'pendingSaleEvents' => $pendingSaleEvents]);
    }
}
