<?php

namespace App\Controllers;

use App\Libraries\SettlementService;
use App\Models\SettlementModel;
use App\Models\SaleEventModel;

class SettlementController extends BaseController
{
    private SettlementService $settlement;
    private SettlementModel $settlementModel;
    private SaleEventModel $saleEventModel;

    public function __construct()
    {
        $this->settlement = new SettlementService();
        $this->settlementModel = new SettlementModel();
        $this->saleEventModel = new SaleEventModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function show(string $settlementId)
    {
        $s = $this->settlementModel->find($settlementId);
        if (!$s) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $saleEvent = $this->saleEventModel->find($s['sale_event_id']);
        $invoices = (new \App\Libraries\InvoiceService())->findForSettlement($settlementId);
        $chronicle = (new \App\Libraries\ChronicleService())->findForSaleEvent($s['sale_event_id']);

        $db = \Config\Database::connect();
        $dispute = $db->table('dispute')->where('sale_event_id', $s['sale_event_id'])->orderBy('created_at', 'DESC')->get()->getRowArray();

        // Phase 3A: a real timeline of audit trail events relevant to
        // this specific settlement/sale event — the underlying BR-05
        // hash-chained data already exists (D-45), this just surfaces
        // it scoped to one transaction rather than only the platform-
        // wide log at /admin/audit-log.
        $auditEvents = $db->table('audit_log')
            ->like('payload', $settlementId)
            ->orLike('payload', $s['sale_event_id'])
            ->orderBy('sequence_number', 'ASC')
            ->get()->getResultArray();

        return view('settlement/show', [
            'title' => 'Settlement — AdwitiX', 'settlement' => $s, 'saleEvent' => $saleEvent,
            'callerId' => $this->requireLogin(), 'invoices' => $invoices,
            'dispute' => $dispute, 'auditEvents' => $auditEvents, 'chronicle' => $chronicle,
        ]);
    }

    public function confirmSellerNoc(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->settlement->confirmSellerNoc($settlementId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function confirmBuyerNoc(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        try {
            $this->settlement->confirmBuyerNoc($settlementId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function rateAsBuyer(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $outcome = $this->request->getPost('outcome');
        $reason = $this->request->getPost('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, $partyId, 'buyer', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    public function rateAsSeller(string $settlementId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $outcome = $this->request->getPost('outcome');
        $reason = $this->request->getPost('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, $partyId, 'seller', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }

    // ⚠️ DEV-ONLY: BR-39's real 7-day stall wait can't be tested live —
    // forces the flag check to run immediately. Gated behind tenantAdmin.
    public function devFlagStalled()
    {
        $flagged = $this->settlement->flagStalledSettlements();
        return $this->response->setJSON(['flagged' => $flagged]);
    }

    // Real admin action (once flagged), not a time-skip — genuinely
    // gated behind tenantAdmin since force-resolving is an administrative act.
    public function forceResolve(string $settlementId)
    {
        try {
            $this->settlement->forceResolveStalled($settlementId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/settlements/{$settlementId}")->with('error', $e->getMessage());
        }
        return redirect()->to("/settlements/{$settlementId}");
    }
}
