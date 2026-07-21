<?php

namespace App\Controllers;

use App\Libraries\DisputeService;
use App\Models\DisputeModel;
use App\Models\SaleEventModel;

class DisputeController extends BaseController
{
    private DisputeService $dispute;
    private DisputeModel $disputeModel;
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->dispute = new DisputeService();
        $this->disputeModel = new DisputeModel();
        $this->saleEventModel = new SaleEventModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function fileForm(string $saleEventId)
    {
        if (!$this->requireLogin()) return redirect()->to('/login');
        return view('dispute/file', ['title' => 'File a Dispute — eBid Hub', 'saleEventId' => $saleEventId]);
    }

    public function fileSubmit(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $category = $this->request->getPost('category');
        $description = $this->request->getPost('description');

        try {
            $d = $this->dispute->fileDispute($saleEventId, $partyId, $category, $description);
        } catch (\RuntimeException $e) {
            return redirect()->to("/sale-events/{$saleEventId}/dispute")->with('error', $e->getMessage());
        }

        return redirect()->to("/disputes/{$d['id']}");
    }

    public function show(string $disputeId)
    {
        $partyId = $this->requireLogin();
        $d = $this->disputeModel->find($disputeId);
        if (!$d) throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();

        return view('dispute/show', [
            'title' => 'Dispute — eBid Hub', 'dispute' => $d,
            'evidence' => $this->dispute->getEvidence($disputeId),
            'callerId' => $partyId,
        ]);
    }

    public function submitEvidence(string $disputeId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->dispute->submitEvidence($disputeId, $partyId, $this->request->getPost('content'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/disputes/{$disputeId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/disputes/{$disputeId}");
    }

    // Authorization is checked inside DisputeService itself (category-aware
    // — Tenant Admin vs Super Admin), not by a route filter, since a single
    // route filter can't branch by the dispute's own category.
    public function rule(string $disputeId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->dispute->ruleOnDispute(
                $disputeId, $partyId, $this->request->getPost('outcome'),
                $this->request->getPost('rationale'), $this->request->getPost('at_fault_party_id') ?: null
            );
        } catch (\RuntimeException $e) {
            return redirect()->to("/disputes/{$disputeId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/disputes/{$disputeId}");
    }

    public function appeal(string $disputeId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->dispute->fileAppeal($disputeId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/disputes/{$disputeId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/disputes/{$disputeId}");
    }

    public function ruleOnAppeal(string $disputeId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->dispute->ruleOnAppeal($disputeId, $partyId, $this->request->getPost('rationale'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/disputes/{$disputeId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/disputes/{$disputeId}");
    }
}
