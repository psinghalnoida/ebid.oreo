<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\SellerApplicationModel;

class TenantAdminController extends BaseController
{
    public function dashboard(string $tenantId)
    {
        $tenantModel = new TenantModel();
        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
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

        return view('tenant_admin/dashboard', [
            'title' => 'Tenant Admin — ' . $tenant['name'],
            'tenant' => $tenant,
            'pendingListings' => $pendingListings,
            'pendingSaleEvents' => $pendingSaleEvents,
            'pendingSellers' => $pendingSellers,
            'openDisputes' => $openDisputes,
            'stalledSettlements' => $stalledSettlements,
            'highValueDisposals' => $highValueDisposals,
        ]);
    }

    // PR-09 step 7: "the Tenant Admin reviews the authentic media
    // catalog and thumbnail in the Verification Console" — previously
    // the only pending-listings view was a bare text link with no
    // thumbnail or media counts at all. Review/approve/reject itself
    // still happens on the existing listing detail page (already fully
    // built, including the closed-list reject reason) — this is the
    // entry point with real visual context, not a duplicate workflow.
    public function verification(string $tenantId)
    {
        $tenantModel = new TenantModel();
        $tenant = $tenantModel->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
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

        return view('tenant_admin/verification', [
            'title' => 'Verification Console — ' . $tenant['name'],
            'tenant' => $tenant, 'pending' => $pending,
        ]);
    }
}
