<?php

namespace App\Controllers;

use App\Models\PartyModel;
use App\Models\PartyRoleModel;

// Phase 3D: platform-wide Super Admin user directory — was previously
// only reachable per-tenant (SellerManagementController) or not at all
// for buyers/inspectors/tenant admins. Search + detail only; no write
// actions here that don't already exist elsewhere (delisting, rating
// review, standing review all keep their own governed controllers).
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

        return view('admin/users_list', [
            'title' => 'Users — eBid Hub',
            'users' => $users,
            'q' => $q,
        ]);
    }

    public function detail(string $partyId)
    {
        $party = (new PartyModel())->find($partyId);
        if (!$party) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $roles = (new PartyRoleModel())->findActiveRolesForParty($partyId);

        $db = \Config\Database::connect();
        // tenant names for any tenant-scoped roles, so the view doesn't
        // have to show bare UUIDs
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

        return view('admin/user_detail', [
            'title' => 'User — eBid Hub',
            'party' => $party,
            'roles' => $roles,
            'tenantNames' => $tenantNames,
            'purchases' => $purchases,
            'sales' => $sales,
            'disputes' => $disputes,
            'ratingEvents' => $ratingEvents,
        ]);
    }
}
