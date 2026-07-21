<?php

namespace App\Controllers;

use App\Libraries\ListingLifecycleService;
use App\Models\ListingModel;
use App\Models\TenantModel;

class ListingController extends BaseController
{
    private ListingLifecycleService $lifecycle;
    private ListingModel $listingModel;
    private TenantModel $tenantModel;

    public function __construct()
    {
        $this->lifecycle = new ListingLifecycleService();
        $this->listingModel = new ListingModel();
        $this->tenantModel = new TenantModel();
    }

    private function requireLogin()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) {
            return null;
        }
        return $partyId;
    }

    public function createForm()
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }
        // Dev convenience: for now, list any tenant to attach to.
        // Tenant selection/scoping by seller role (BR-09) is not yet built.
        $tenants = $this->tenantModel->findAll();
        return view('listing/create', ['title' => 'List an Asset — eBid Hub', 'tenants' => $tenants]);
    }

    public function createSubmit()
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }

        try {
            $listing = $this->listingModel->createListing([
                'tenant_id' => $this->request->getPost('tenant_id'),
                'seller_party_id' => $sellerId,
                'physical_condition' => $this->request->getPost('physical_condition'),
                'category' => $this->request->getPost('category'),
                'subcategory' => $this->request->getPost('subcategory') ?: null,
                'quantity' => $this->request->getPost('quantity'),
                'quantity_basis' => 'unit',
                'make_model' => $this->request->getPost('make_model'),
                'yard_location_address' => $this->request->getPost('yard_location_address'),
                'yard_location_pin' => $this->request->getPost('yard_location_pin'),
                'media_tier' => $this->request->getPost('media_tier') ?: 'certified_by_seller',
            ]);
        } catch (\Throwable $e) {
            return view('listing/create', [
                'title' => 'List an Asset — eBid Hub',
                'tenants' => $this->tenantModel->findAll(),
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->to("/listings/{$listing['id']}");
    }

    public function show(string $listingId)
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();
        $saleEvent = $db->table('sale_event')
            ->where('listing_id', $listingId)
            ->whereIn('status', ['pending_approval', 'grace_period', 'active', 'closed_sold'])
            ->orderBy('created_at', 'DESC')
            ->get()->getRowArray();

        $offers = [];
        $expressState = null;
        $settlementRecord = null;
        $media = (new \App\Models\ListingMediaModel())->findForListing($listingId);
        if ($saleEvent && $saleEvent['status'] === 'closed_sold') {
            $settlementRecord = (new \App\Models\SettlementModel())->findBySaleEvent($saleEvent['id']);
        }
        if ($saleEvent && $saleEvent['sale_format'] === 'buy_now') {
            $offerModel = new \App\Models\OfferModel();
            $offers = $offerModel->findForSaleEvent($saleEvent['id']);
        }
        if ($saleEvent && $saleEvent['sale_format'] === 'express') {
            $expressService = new \App\Libraries\ExpressAuctionService();
            $expressState = [
                'pledgeCount' => $expressService->pledgeCount($saleEvent['id']),
                'biddingOpen' => $expressService->isBiddingOpen($saleEvent),
            ];
        }

        return view('listing/show', [
            'title' => 'Listing — eBid Hub', 'listing' => $listing, 'saleEvent' => $saleEvent,
            'offers' => $offers, 'expressState' => $expressState, 'media' => $media,
            'isOwner' => session()->get('logged_in_party_id') === $listing['seller_party_id'],
            'minPhotos' => \App\Libraries\MediaService::minPhotos(),
            'settlementRecord' => $settlementRecord,
        ]);
    }

    // BR-13: submit for Tenant Admin review
    public function submitForApproval(string $listingId)
    {
        $this->lifecycle->submitForApproval($listingId);
        return redirect()->to("/listings/{$listingId}");
    }

    // BR-09: Tenant Admin approval — access enforced by the tenantAdmin
    // route filter, not by this method. If execution reaches here, the
    // caller has already been confirmed as the Tenant Admin for this
    // listing's tenant.
    public function approve(string $listingId)
    {
        $this->lifecycle->approve($listingId);
        return redirect()->to("/listings/{$listingId}");
    }

    public function reject(string $listingId)
    {
        $reason = $this->request->getPost('reason') ?: 'insufficient photos';
        $this->lifecycle->reject($listingId, $reason);
        return redirect()->to("/listings/{$listingId}");
    }
}
