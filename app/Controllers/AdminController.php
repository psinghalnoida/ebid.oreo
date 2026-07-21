<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\DisputeModel;
use App\Models\SettlementModel;

class AdminController extends BaseController
{
    public function dashboard()
    {
        $tenantModel = new TenantModel();
        $disputeModel = new DisputeModel();
        $settlementModel = new SettlementModel();

        return view('admin/dashboard', [
            'title' => 'Super Admin — eBid Hub',
            'tenants' => $tenantModel->findAll(),
            'openDisputes' => $disputeModel->whereIn('status', ['filed', 'evidence_window', 'appealed'])->countAllResults(),
            'stalledSettlements' => $settlementModel->where('status', 'stalled')->countAllResults(),
        ]);
    }
}
