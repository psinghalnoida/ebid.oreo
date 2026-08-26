<?php

namespace App\Controllers;

use App\Libraries\DisputeService;
use App\Libraries\UserAuthContext;
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

    public function fileSubmit(string $saleEventId)
    {
        $partyId = UserAuthContext::partyId();
        $category = $this->input('category');
        $description = $this->input('description');

        try {
            $d = $this->dispute->fileDispute($saleEventId, $partyId, $category, $description);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'file_failed', $e->getMessage());
        }

        return $this->response->setStatusCode(201)->setJSON(['dispute' => $d]);
    }

    public function show(string $disputeId)
    {
        $d = $this->disputeModel->find($disputeId);
        if (!$d) {
            return $this->jsonError(404, 'not_found', 'Dispute not found.');
        }

        return $this->response->setJSON([
            'dispute' => $d,
            'evidence' => $this->dispute->getEvidence($disputeId),
            'callerId' => UserAuthContext::partyId(),
        ]);
    }

    public function submitEvidence(string $disputeId)
    {
        try {
            $this->dispute->submitEvidence($disputeId, UserAuthContext::partyId(), $this->input('content'));
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'evidence_failed', $e->getMessage());
        }
        return $this->response->setJSON(['dispute' => $this->disputeModel->find($disputeId)]);
    }

    // Authorization is checked inside DisputeService itself (category-aware
    // — Tenant Admin vs Super Admin), not by a route filter, since a single
    // route filter can't branch by the dispute's own category. Runs behind
    // jwtAuth (any authenticated party) for that reason.
    public function rule(string $disputeId)
    {
        try {
            $this->dispute->ruleOnDispute(
                $disputeId, UserAuthContext::partyId(), $this->input('outcome'),
                $this->input('rationale'), $this->input('at_fault_party_id') ?: null
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'rule_failed', $e->getMessage());
        }
        return $this->response->setJSON(['dispute' => $this->disputeModel->find($disputeId)]);
    }

    public function appeal(string $disputeId)
    {
        try {
            $this->dispute->fileAppeal($disputeId, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'appeal_failed', $e->getMessage());
        }
        return $this->response->setJSON(['dispute' => $this->disputeModel->find($disputeId)]);
    }

    // Access enforced by the jwtSuperAdmin route filter.
    public function ruleOnAppeal(string $disputeId)
    {
        try {
            $this->dispute->ruleOnAppeal($disputeId, UserAuthContext::partyId(), $this->input('rationale'));
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'rule_appeal_failed', $e->getMessage());
        }
        return $this->response->setJSON(['dispute' => $this->disputeModel->find($disputeId)]);
    }
}
