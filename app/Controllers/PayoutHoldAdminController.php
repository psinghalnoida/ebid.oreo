<?php

namespace App\Controllers;

use App\Libraries\PayoutAccountService;

// BR-50(c): platform-wide view for SaaS Admin — the same holds are also
// reviewable inline on a settlement's own page (SettlementController) by
// that settlement's Tenant Admin, per BR-50's "Tenant Admin OR SaaS Admin"
// dual authority. This controller is the SaaS-Admin cross-tenant surface.
class PayoutHoldAdminController extends BaseController
{
    public function pendingList()
    {
        $db = \Config\Database::connect();

        $pending = $db->table('payout_hold ph')
            ->select('ph.*, p.mobile_number, p.full_name, t.name as tenant_name')
            ->join('party p', 'p.id = ph.party_id')
            ->join('settlement s', 's.id = ph.settlement_id')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('tenant t', 't.id = se.tenant_id')
            ->where('ph.status', 'pending')
            ->orderBy('ph.created_at', 'ASC')
            ->get()->getResultArray();

        $reviewed = $db->table('payout_hold ph')
            ->select('ph.*, p.mobile_number, p.full_name, t.name as tenant_name')
            ->join('party p', 'p.id = ph.party_id')
            ->join('settlement s', 's.id = ph.settlement_id')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('tenant t', 't.id = se.tenant_id')
            ->where('ph.status !=', 'pending')
            ->orderBy('ph.reviewed_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        return view('admin/payout_holds', ['title' => 'Payout Holds — eBid Hub', 'pending' => $pending, 'reviewed' => $reviewed]);
    }

    public function decide(string $holdId)
    {
        $superAdminId = session()->get('logged_in_party_id');
        $outcome = $this->request->getPost('outcome') === 'release' ? 'released' : 'rejected';
        $notes = $this->request->getPost('notes');

        try {
            $hold = (new PayoutAccountService())->reviewHold($holdId, $superAdminId, $outcome, $notes);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/payout-holds')->with('error', $e->getMessage());
        }

        if ($outcome === 'released') {
            (new \App\Libraries\SettlementService())->retryPayoutHold($hold['settlement_id']);
        }

        return redirect()->to('/admin/payout-holds');
    }
}
