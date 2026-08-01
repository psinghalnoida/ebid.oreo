<?php

namespace App\Controllers;

use App\Libraries\AmlMonitoringService;
use App\Models\AmlFlagModel;

// BR-54/PR-31: gated entirely behind the superAdmin filter at the route
// level — PR-31 is explicit that AML flags are visible only to SaaS
// Admin, never a Tenant Admin or the flagged User.
class AmlController extends BaseController
{
    public function index()
    {
        $flagModel = new AmlFlagModel();
        return view('admin/aml', [
            'title' => 'AML Monitoring — AdwitiX',
            'open' => $flagModel->findOpen(),
            'reviewed' => $flagModel->findReviewed(),
        ]);
    }

    public function review(string $flagId)
    {
        $superAdminId = session()->get('super_admin_party_id');
        $decision = $this->request->getPost('decision');
        $strReference = $this->request->getPost('str_reference') ?: null;
        $notes = $this->request->getPost('notes');

        try {
            (new AmlMonitoringService())->reviewFlag($flagId, $superAdminId, $decision, $strReference, $notes);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/aml')->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/aml');
    }
}
