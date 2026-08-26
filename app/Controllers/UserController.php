<?php

namespace App\Controllers;

use App\Models\PartyModel;
use App\Models\PartyRoleModel;
use App\Models\TenantModel;

// Phase 3D: platform-wide Super Admin user directory. All routes
// jwtSuperAdmin-gated.
class UserController extends BaseController
{
    public function index()
    {
        $partyModel = new PartyModel();
        $q = trim((string) $this->request->getGet('q'));

        $builder = $partyModel->where('archived_at', null)->orderBy('mobile_number', 'ASC');
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('mobile_number', $q)
                ->orLike('full_name', $q)
                ->orLike('recovery_email', $q)
                ->orLike('org_gstin', $q)
                ->groupEnd();
        }
        $users = $builder->findAll(100);

        return $this->response->setJSON(['users' => $users, 'q' => $q]);
    }

    public function detail(string $partyId)
    {
        $party = (new PartyModel())->find($partyId);
        if (!$party) {
            return $this->jsonError(404, 'not_found', 'Party not found.');
        }

        $roles = (new PartyRoleModel())->findActiveRolesForParty($partyId);

        $db = \Config\Database::connect();
        $tenantIds = array_filter(array_column($roles, 'tenant_id'));
        $tenantNames = [];
        if (!empty($tenantIds)) {
            $rows = $db->table('tenant')->select('id, name')->whereIn('id', $tenantIds)->get()->getResultArray();
            foreach ($rows as $r) {
                $tenantNames[$r['id']] = $r['name'];
            }
        }

        $purchases = $db->table('settlement s')
            ->select('s.id, s.status, s.final_price, s.created_at')
            ->where('s.buyer_party_id', $partyId)
            ->orderBy('s.created_at', 'DESC')
            ->limit(20)->get()->getResultArray();

        $sales = $db->table('settlement s')
            ->select('s.id, s.status, s.final_price, s.created_at')
            ->where('s.seller_party_id', $partyId)
            ->orderBy('s.created_at', 'DESC')
            ->limit(20)->get()->getResultArray();

        $disputes = $db->table('dispute')
            ->where('filed_by_party_id', $partyId)
            ->orWhere('respondent_party_id', $partyId)
            ->orderBy('created_at', 'DESC')
            ->limit(20)->get()->getResultArray();

        $ratingEvents = $db->table('rating_event')
            ->where('party_id', $partyId)
            ->orderBy('created_at', 'DESC')
            ->limit(20)->get()->getResultArray();

        return $this->response->setJSON([
            'party' => $party, 'roles' => $roles, 'tenantNames' => $tenantNames,
            'purchases' => $purchases, 'sales' => $sales, 'disputes' => $disputes,
            'ratingEvents' => $ratingEvents, 'tenants' => (new TenantModel())->findAll(),
        ]);
    }

    // PR-08: Super Admin web UI to promote a Tenant Admin. Wraps the same
    // PartyRoleModel::promoteTenantAdmin() logic (BR-44 auto-demotion of
    // whoever previously held the role for that tenant included).
    public function promoteTenantAdmin(string $partyId)
    {
        $partyModel = new PartyModel();
        $party = $partyModel->find($partyId);
        if (!$party) {
            return $this->jsonError(404, 'not_found', 'Party not found.');
        }

        $tenantId = (string) $this->input('tenant_id');
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(422, 'invalid_tenant', 'A valid tenant must be selected.');
        }

        $roleModel = new PartyRoleModel();
        $existing = $roleModel->findActiveTenantAdmin($tenantId);

        $roleModel->promoteTenantAdmin($partyId, $tenantId);
        (new \App\Libraries\AuditLogService())->log('admin.tenant_admin_granted', $partyId, [
            'tenantId' => $tenantId, 'tenantName' => $tenant['name'],
            'demotedPreviousAdminId' => $existing['party_id'] ?? null, 'grantedViaCli' => false,
        ], $this->request->getIPAddress(), (string) $this->request->getUserAgent());

        return $this->response->setJSON(['message' => "Granted Tenant Admin for \"{$tenant['name']}\"."]);
    }
}
