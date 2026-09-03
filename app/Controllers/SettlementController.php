<?php

namespace App\Controllers;

use App\Libraries\SettlementService;
use App\Libraries\UserAuthContext;
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

    public function show(string $settlementId)
    {
        $s = $this->settlementModel->find($settlementId);
        if (!$s) {
            return $this->jsonError(404, 'not_found', 'Settlement not found.');
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

        return $this->apiResponse([
            'settlement' => $s, 'saleEvent' => $saleEvent,
            'callerId' => UserAuthContext::partyId(), 'invoices' => $invoices,
            'dispute' => $dispute, 'auditEvents' => $auditEvents, 'chronicle' => $chronicle,
        ]);
    }

    public function confirmSellerNoc(string $settlementId)
    {
        try {
            $this->settlement->confirmSellerNoc($settlementId, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'confirm_failed', $e->getMessage());
        }
        return $this->apiResponse(['settlement' => $this->settlementModel->find($settlementId)]);
    }

    public function confirmBuyerNoc(string $settlementId)
    {
        try {
            $this->settlement->confirmBuyerNoc($settlementId, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'confirm_failed', $e->getMessage());
        }
        return $this->apiResponse(['settlement' => $this->settlementModel->find($settlementId)]);
    }

    public function rateAsBuyer(string $settlementId)
    {
        $outcome = $this->input('outcome');
        $reason = $this->input('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, UserAuthContext::partyId(), 'buyer', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'rating_failed', $e->getMessage());
        }
        return $this->apiResponse(['settlement' => $this->settlementModel->find($settlementId)]);
    }

    public function rateAsSeller(string $settlementId)
    {
        $outcome = $this->input('outcome');
        $reason = $this->input('reason') ?: null;
        try {
            $this->settlement->submitRating($settlementId, UserAuthContext::partyId(), 'seller', $outcome, $reason);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'rating_failed', $e->getMessage());
        }
        return $this->apiResponse(['settlement' => $this->settlementModel->find($settlementId)]);
    }

    // ⚠️ DEV-ONLY: BR-39's real 7-day stall wait can't be tested live —
    // forces the flag check to run immediately. Gated behind jwtSuperAdmin
    // (platform-wide sweep, not scoped to one tenant).
    public function devFlagStalled()
    {
        $flagged = $this->settlement->flagStalledSettlements();
        return $this->apiResponse(['flagged' => $flagged]);
    }

    // Real admin action (once flagged), not a time-skip — genuinely
    // gated behind jwtTenantAdmin since force-resolving is an
    // administrative act.
    public function forceResolve(string $settlementId)
    {
        try {
            $this->settlement->forceResolveStalled($settlementId);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'force_resolve_failed', $e->getMessage());
        }
        return $this->apiResponse(['settlement' => $this->settlementModel->find($settlementId)]);
    }
}
