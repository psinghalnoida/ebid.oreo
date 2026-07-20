<?php

namespace App\Controllers;

use App\Libraries\ListingLifecycleService;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;

class SaleEventController extends BaseController
{
    private ListingLifecycleService $lifecycle;
    private ListingModel $listingModel;
    private SaleEventModel $saleEventModel;
    private BidModel $bidModel;

    public function __construct()
    {
        $this->lifecycle = new ListingLifecycleService();
        $this->listingModel = new ListingModel();
        $this->saleEventModel = new SaleEventModel();
        $this->bidModel = new BidModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // BR-12: attach an Easy Auction to an approved (upcoming) listing
    public function createSubmit(string $listingId)
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }

        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['status'] !== 'upcoming') {
            return redirect()->to("/listings/{$listingId}")->with('error', 'Listing must be approved (upcoming) before attaching a sale event.');
        }

        $ern = 'EH-' . strtoupper(substr($listingId, 0, 8));
        $saleEvent = $this->saleEventModel->createSaleEvent([
            'listing_id' => $listingId,
            'tenant_id' => $listing['tenant_id'],
            'ern' => $ern,
            'sale_format' => 'easy',
            'reserve_value' => $this->request->getPost('reserve_value'),
            'result_mode' => 'instant_close',
        ]);

        // BR-13: listing moves to active once a sale system is attached
        $this->listingModel->transitionStatus($listingId, 'active');

        return redirect()->to("/listings/{$listingId}");
    }

    // BR-09: Tenant Admin approval — access enforced by the tenantAdmin
    // route filter (resource type 'saleEvent').
    public function approve(string $saleEventId)
    {
        $this->lifecycle->approveSaleEvent($saleEventId);
        $saleEvent = $this->saleEventModel->find($saleEventId);
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // ⚠️ DEV-ONLY: BR-14's real 60-minute grace window can't be waited out
    // in a live demo/test session — this forces the freeze immediately.
    // Must not exist in a production build; the real transition is
    // time-based via a scheduled job. Now also gated behind the
    // tenantAdmin filter, consistent with other administrative actions,
    // though the underlying time-skip mechanism itself remains a stand-in.
    public function devForceFreeze(string $saleEventId)
    {
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $this->saleEventModel->update($saleEventId, [
            'grace_period_ends_at' => date('Y-m-d H:i:s', time() - 1),
        ]);
        $this->lifecycle->freezeAfterGrace($saleEventId);
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }
}
