<?php

namespace App\Controllers;

use App\Libraries\AmlMonitoringService;
use App\Models\AmlFlagModel;

// BR-54/PR-31: every route here sits behind the superAdmin filter (see
// Routes.php) — flags are visible ONLY to SaaS Admin, per BR-54's own
// text, never to the flagged User or any Tenant Admin. There is
// deliberately no other read path to this table anywhere in the app.
class AmlAdminController extends BaseController
{
    public function pendingList()
    {
        $flagModel = new AmlFlagModel();
        $db = \Config\Database::connect();

        $open = $db->table('aml_flag af')
            ->select('af.*, p.mobile_number, p.full_name')
            ->join('party p', 'p.id = af.party_id')
            ->where('af.status', 'open')
            ->orderBy('af.created_at', 'ASC')
            ->get()->getResultArray();

        $reviewed = $db->table('aml_flag af')
            ->select('af.*, p.mobile_number, p.full_name')
            ->join('party p', 'p.id = af.party_id')
            ->where('af.status !=', 'open')
            ->orderBy('af.reviewed_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        return view('admin/aml_flags', ['title' => 'AML Monitoring — eBid Hub', 'open' => $open, 'reviewed' => $reviewed]);
    }

    public function decide(string $flagId)
    {
        $superAdminId = session()->get('logged_in_party_id');
        $outcome = $this->request->getPost('outcome');
        $notes = $this->request->getPost('notes');
        $strFiled = $this->request->getPost('str_filed') === '1';
        $strReference = $this->request->getPost('str_reference') ?: null;

        try {
            (new AmlMonitoringService())->review($flagId, $superAdminId, $outcome, $notes, $strFiled, $strReference);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/aml-flags')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/aml-flags');
    }
}
