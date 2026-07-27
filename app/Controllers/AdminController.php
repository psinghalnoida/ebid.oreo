<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\DisputeModel;
use App\Models\SettlementModel;
use App\Models\AmlFlagModel;

class AdminController extends BaseController
{
    public function dashboard()
    {
        $tenantModel = new TenantModel();
        $disputeModel = new DisputeModel();
        $settlementModel = new SettlementModel();
        $amlFlagModel = new AmlFlagModel();

        // BR-49: "included in the Super Admin's audit console" —
        // platform-wide, not scoped to any single tenant.
        $db = \Config\Database::connect();
        $highValueDisposals = $db->table('high_value_disposal_record hvdr')
            ->select('hvdr.*, t.name as tenant_name')
            ->join('tenant t', 't.id = hvdr.tenant_id')
            ->orderBy('hvdr.created_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        return view('admin/dashboard', [
            'title' => 'Super Admin — eBid Hub',
            'tenants' => $tenantModel->findAll(),
            'openDisputes' => $disputeModel->whereIn('status', ['filed', 'evidence_window', 'appealed'])->countAllResults(),
            'stalledSettlements' => $settlementModel->where('status', 'stalled')->countAllResults(),
            'openAmlFlags' => $amlFlagModel->where('status', 'open')->countAllResults(),
            'highValueDisposals' => $highValueDisposals,
        ]);
    }
}
