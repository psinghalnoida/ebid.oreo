<?php

namespace App\Libraries;

use App\Models\PartyRoleModel;

// PR-16: "If master KYC status is suspended or a compliance flag is
// revoked: an automatic global lockout cascades to every role held by
// that Party." Called from the two real, already-reachable compliance
// events that revoke a party's standing: KycService::reviewDossier()'s
// suspend branch, and RatingService::delistSellerForFraud().
//
// A dedicated admin action to revoke an individual compliance flag
// (pan/gstin/aadhaar/bank/email -- the counterpart to
// KycService::verifyComplianceFlag()) doesn't exist yet, so that
// specific trigger wording stays dormant until such an action is
// built -- same honest-gap treatment as CascadeService::forfeitHold(),
// whose only caller is also unreached in production today.
class ComplianceLockoutService
{
    private PartyRoleModel $roleModel;

    public function __construct()
    {
        $this->roleModel = new PartyRoleModel();
    }

    public function cascadeLockout(string $partyId, string $reason, ?string $actorId = null): array
    {
        $activeRoles = $this->roleModel->findActiveRolesForParty($partyId);
        $lockedOutRoles = [];
        foreach ($activeRoles as $role) {
            $this->roleModel->update($role['id'], ['revoked_at' => date('Y-m-d H:i:s')]);
            $lockedOutRoles[] = ['role' => $role['role'], 'tenantId' => $role['tenant_id']];
        }

        (new AuditLogService())->log('party.compliance_lockout_cascaded', $actorId, [
            'partyId' => $partyId, 'reason' => $reason, 'lockedOutRoles' => $lockedOutRoles,
        ]);

        return ['lockedOutRoleCount' => count($lockedOutRoles), 'lockedOutRoles' => $lockedOutRoles];
    }
}
