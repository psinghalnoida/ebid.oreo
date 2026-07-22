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

        return view('tenant_admin/dashboard', [
            'title' => 'Tenant Admin — ' . $tenant['name'],
            'tenant' => $tenant,
            'pendingListings' => $pendingListings,
            'pendingSaleEvents' => $pendingSaleEvents,
            'pendingSellers' => $pendingSellers,
            'openDisputes' => $openDisputes,
            'stalledSettlements' => $stalledSettlements,
        ]);
    }
}
