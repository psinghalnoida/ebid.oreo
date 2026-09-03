<?php

namespace App\Controllers;

use App\Libraries\AmlMonitoringService;
use App\Libraries\UserAuthContext;
use App\Models\AmlFlagModel;

// BR-54/PR-31: jwtSuperAdmin-gated — PR-31 is explicit that AML flags
// are visible only to SaaS Admin, never a Tenant Admin or the flagged User.
class AmlController extends BaseController
{
    public function index()
    {
        $flagModel = new AmlFlagModel();
        return $this->apiResponse([
            'open' => $flagModel->findOpen(),
            'reviewed' => $flagModel->findReviewed(),
        ]);
    }

    public function review(string $flagId)
    {
        $superAdminId = UserAuthContext::partyId();
        $decision = $this->input('decision');
        $strReference = $this->input('str_reference') ?: null;
        $notes = $this->input('notes');

        try {
            (new AmlMonitoringService())->reviewFlag($flagId, $superAdminId, $decision, $strReference, $notes);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'review_failed', $e->getMessage());
        }

        return $this->apiResponse(['flag' => (new AmlFlagModel())->find($flagId)]);
    }
}
