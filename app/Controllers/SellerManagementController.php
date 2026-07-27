<?php

namespace App\Controllers;

use App\Libraries\StandingReviewService;
use App\Models\TenantModel;
use App\Models\PartyModel;

// BR-61: a real Seller Management view for the Tenant Admin, built on
// top of the ACTUAL Standing Review system (StandingReviewService,
// D-60) — the complaint/CBS counters and dispute-table-backed cases
// that already exist, not a new parallel violations/reviews schema.
class SellerManagementController extends BaseController
{
    public function list(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();
        $sellers = $db->table('seller_application sa')
            ->select('sa.party_id, sa.decided_at as approved_at, p.mobile_number, p.seller_star_rating,
                      p.standing_review_complaint_count, p.standing_review_cbs_offense_count, p.seller_delisted_at')
            ->join('party p', 'p.id = sa.party_id')
            ->where('sa.tenant_id', $tenantId)
            ->where('sa.status', 'approved')
            ->orderBy('p.standing_review_complaint_count', 'DESC')
            ->get()->getResultArray();

        foreach ($sellers as &$seller) {
            $seller['salesOnTenant'] = $db->table('settlement s')
                ->join('sale_event se', 'se.id = s.sale_event_id')
                ->where('se.tenant_id', $tenantId)
                ->where('s.seller_party_id', $seller['party_id'])
                ->countAllResults();

            $seller['hasOpenCase'] = $db->table('dispute')
                ->where('category', 'standing_review')
                ->where('respondent_party_id', $seller['party_id'])
                ->whereIn('status', ['filed', 'evidence_window', 'appealed'])
                ->countAllResults() > 0;
        }
        unset($seller);

        return view('tenant_admin/sellers_list', ['title' => 'Seller Management — ' . $tenant['name'], 'tenant' => $tenant, 'sellers' => $sellers]);
    }

    public function detail(string $tenantId, string $sellerId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        $seller = (new PartyModel())->find($sellerId);
        if (!$tenant || !$seller) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();

        $sales = $db->table('settlement s')
            ->select('s.id, s.status, s.final_price, s.created_at, se.sale_format, l.category')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('se.tenant_id', $tenantId)
            ->where('s.seller_party_id', $sellerId)
            ->orderBy('s.created_at', 'DESC')
            ->get()->getResultArray();

        // BR-35: every real named rating consequence this seller has
        // received — CBS violations, dispute rulings, confirmed fraud —
        // genuinely queryable from the same table BR-35 wired, not a
        // separate violations log.
        $violations = $db->table('rating_event')
            ->where('party_id', $sellerId)
            ->where('rating_role', 'seller_star_rating')
            ->where('event_type', 'downgrade')
            ->orderBy('created_at', 'DESC')
            ->get()->getResultArray();

        $openCase = $db->table('dispute')
            ->where('category', 'standing_review')
            ->where('respondent_party_id', $sellerId)
            ->whereIn('status', ['filed', 'evidence_window', 'appealed'])
            ->get()->getRowArray();

        return view('tenant_admin/seller_detail', [
            'title' => 'Seller — ' . $seller['mobile_number'],
            'tenant' => $tenant, 'seller' => $seller, 'sales' => $sales,
            'violations' => $violations, 'openCase' => $openCase,
        ]);
    }

    public function initiateReview(string $tenantId, string $sellerId)
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $reason = $this->request->getPost('reason');
        if (!$reason) {
            return redirect()->back()->with('error', 'A reason is required to initiate a Standing Review case.');
        }

        try {
            $case = (new StandingReviewService())->openCase($sellerId, $reason, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/tenants/{$tenantId}/sellers/{$sellerId}")->with('error', $e->getMessage());
        }

        return redirect()->to("/admin/standing-review/{$case['id']}");
    }
}
