<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\DisputeModel;
use App\Models\SettlementModel;
use App\Models\AmlFlagModel;

class AdminController extends BaseController
{
    public function dashboard()
    {
        $tenantModel = new TenantModel();
        $disputeModel = new DisputeModel();
        $settlementModel = new SettlementModel();
        $amlFlagModel = new AmlFlagModel();

        // BR-49: "included in the Super Admin's audit console" —
        // platform-wide, not scoped to any single tenant.
        $db = \Config\Database::connect();
        $highValueDisposals = $db->table('high_value_disposal_record hvdr')
            ->select('hvdr.*, t.name as tenant_name')
            ->join('tenant t', 't.id = hvdr.tenant_id')
            ->orderBy('hvdr.created_at', 'DESC')
            ->limit(50)
            ->get()->getResultArray();

        $todayStart = date('Y-m-d 00:00:00');

        $listingsByFormatToday = $db->table('sale_event')
            ->select('sale_format, COUNT(*) as cnt')
            ->where('created_at >=', $todayStart)
            ->groupBy('sale_format')
            ->get()->getResultArray();

        $bidsPlacedToday = $db->table('bid')->where('placed_at >=', $todayStart)->countAllResults();
        $salesClosedToday = $db->table('settlement')->where('created_at >=', $todayStart)->where('status', 'completed')->countAllResults();
        $disputesFiledToday = $disputeModel->where('created_at >=', $todayStart)->countAllResults();

        // Aging on stalled settlements — how long each has sat unresolved,
        // oldest first, so Super Admin can see what needs escalation.
        $stalledAging = $db->table('settlement s')
            ->select("s.id, s.final_price, s.created_at, EXTRACT(DAY FROM (now() - s.created_at)) as days_stalled")
            ->where('s.status', 'stalled')
            ->orderBy('s.created_at', 'ASC')
            ->limit(20)
            ->get()->getResultArray();

        return view('admin/dashboard', [
            'title' => 'Super Admin — AdwitiX',
            'tenants' => $tenantModel->findAll(),
            'openDisputes' => $disputeModel->whereIn('status', ['filed', 'evidence_window', 'appealed'])->countAllResults(),
            'stalledSettlements' => $settlementModel->where('status', 'stalled')->countAllResults(),
            'openAmlFlags' => $amlFlagModel->where('status', 'open')->countAllResults(),
            'highValueDisposals' => $highValueDisposals,
            'listingsByFormatToday' => $listingsByFormatToday,
            'bidsPlacedToday' => $bidsPlacedToday,
            'salesClosedToday' => $salesClosedToday,
            'disputesFiledToday' => $disputesFiledToday,
            'stalledAging' => $stalledAging,
        ]);
    }

    // Aggregates the same "needs attention" signals that were already
    // scattered across separate admin pages (AML flags, stalled
    // settlements, open disputes, pending payout reviews, pending rating
    // reviews) into one triage view — no new underlying data, just one
    // place to see everything queued for a decision.
    public function alerts()
    {
        $db = \Config\Database::connect();

        $amlFlags = $db->table('aml_flag af')
            ->select('af.*, p.mobile_number')
            ->join('party p', 'p.id = af.party_id')
            ->where('af.status', 'open')
            ->orderBy('af.created_at', 'DESC')->get()->getResultArray();

        $stalledSettlements = $db->table('settlement')->where('status', 'stalled')->orderBy('created_at', 'ASC')->get()->getResultArray();

        $openDisputes = $db->table('dispute')
            ->whereIn('status', ['filed', 'evidence_window', 'appealed'])
            ->orderBy('created_at', 'ASC')->get()->getResultArray();

        $pendingPayoutReviews = $db->table('payout_release_review')->where('status', 'pending')->orderBy('created_at', 'ASC')->get()->getResultArray();

        $pendingRatingReviews = (new \App\Models\RatingEventModel())->findAllPending();

        // Tech Stack §3.10: unacknowledged server-time drift alerts.
        $driftAlerts = (new \App\Models\ServerTimeCheckModel())->findUnacknowledgedDriftAlerts();

        return view('admin/alerts', [
            'title' => 'Alerts — AdwitiX',
            'amlFlags' => $amlFlags,
            'stalledSettlements' => $stalledSettlements,
            'openDisputes' => $openDisputes,
            'pendingPayoutReviews' => $pendingPayoutReviews,
            'pendingRatingReviews' => $pendingRatingReviews,
            'driftAlerts' => $driftAlerts,
        ]);
    }

    // Tech Stack §3.10: Super Admin acknowledges a server-time drift
    // alert, clearing it from the Alerts list.
    public function acknowledgeServerTimeDrift(string $checkId)
    {
        $superAdminId = session()->get('super_admin_party_id');
        try {
            (new \App\Libraries\ServerTimeIntegrityService())->acknowledge($checkId, $superAdminId);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/alerts')->with('error', $e->getMessage());
        }
        return redirect()->to('/admin/alerts')->with('error', 'Drift alert acknowledged.');
    }

    // D-106: "Lot Directory" -- the Custodian had no way to browse every
    // listing platform-wide across every Tenant; only per-tenant pending
    // queues existed. Real filters, real pagination, same shape as
    // TenantController::list()'s existing filterable-admin pattern.
    public function lotDirectory()
    {
        $q = trim((string) $this->request->getGet('q'));
        $tenantId = $this->request->getGet('tenant_id');
        $format = $this->request->getGet('format');
        $status = $this->request->getGet('status');
        $pg = \App\Libraries\Paginator::fromRequest($this->request);

        $svc = new \App\Libraries\AdminDirectoryService();
        $qOrNull = $q !== '' ? $q : null;
        $total = $svc->countListings($qOrNull, $tenantId, $format, $status);
        $listings = $svc->findListings($qOrNull, $tenantId, $format, $status, $pg['perPage'], $pg['offset']);
        $tenants = (new TenantModel())->orderBy('name', 'ASC')->findAll();

        return view('admin/lot_directory', [
            'title' => 'Lot Directory — AdwitiX', 'listings' => $listings, 'tenants' => $tenants,
            'q' => $q, 'tenantId' => $tenantId, 'format' => $format, 'status' => $status,
            'page' => $pg['page'], 'perPage' => $pg['perPage'],
            'totalPages' => \App\Libraries\Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }

    // D-106: "Trading Session Directory" -- same gap, for sale events
    // rather than listings.
    public function tradingSessionDirectory()
    {
        $tenantId = $this->request->getGet('tenant_id');
        $format = $this->request->getGet('format');
        $status = $this->request->getGet('status');
        $pg = \App\Libraries\Paginator::fromRequest($this->request);

        $svc = new \App\Libraries\AdminDirectoryService();
        $total = $svc->countSaleEvents($tenantId, $format, $status);
        $saleEvents = $svc->findSaleEvents($tenantId, $format, $status, $pg['perPage'], $pg['offset']);
        $tenants = (new TenantModel())->orderBy('name', 'ASC')->findAll();

        return view('admin/trading_session_directory', [
            'title' => 'Trading Session Directory — AdwitiX', 'saleEvents' => $saleEvents, 'tenants' => $tenants,
            'tenantId' => $tenantId, 'format' => $format, 'status' => $status,
            'page' => $pg['page'], 'perPage' => $pg['perPage'],
            'totalPages' => \App\Libraries\Paginator::totalPages($total, $pg['perPage']), 'total' => $total,
        ]);
    }
}
