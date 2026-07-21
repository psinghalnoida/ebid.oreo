<?php

namespace App\Controllers;

use App\Libraries\OfferService;
use App\Libraries\EmdService;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\ListingModel;

class OfferController extends BaseController
{
    private OfferService $offers;
    private SaleEventModel $saleEventModel;
    private EmdHoldModel $emdHoldModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->offers = new OfferService();
        $this->saleEventModel = new SaleEventModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->listingModel = new ListingModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function devFundEmd(string $saleEventId)
    {
        $buyerId = $this->requireLogin();
        if (!$buyerId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $baseline = EmdService::calculateBaselineEmd('buy_now', (float) $saleEvent['expected_value'], null);

        $existing = $this->emdHoldModel->findBySaleEventAndParty($saleEventId, $buyerId);
        if (!$existing || $existing['status'] !== 'held') {
            $this->emdHoldModel->createHold($saleEventId, $buyerId, 'van', $baseline);
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function submit(string $saleEventId)
    {
        $buyerId = $this->requireLogin();
        if (!$buyerId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        $amount = (float) $this->request->getPost('amount');

        try {
            $this->offers->submitOffer($saleEventId, $buyerId, $amount);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function withdraw(string $offerId)
    {
        $buyerId = $this->requireLogin();
        if (!$buyerId) {
            return redirect()->to('/login');
        }
        $reason = $this->request->getPost('reason') ?: 'Buyer withdrew';

        try {
            $offer = $this->offers->withdrawOffer($offerId, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $saleEvent = $this->saleEventModel->find($offer['sale_event_id']);
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    // BR-09: this decision belongs to the SELLER, not the Tenant Admin
    // (unlike listing/sale-event approval elsewhere). Currently gated only
    // by login, not by seller-identity — a check that this party actually
    // owns the listing should be added before production use.
    // BR-42: this decision belongs to the SELLER specifically — now
    // enforced, not just gated by login (closes the gap flagged in D-19).
    public function accept(string $saleEventId, string $offerId)
    {
        $sellerId = $this->requireLogin();
        if (!$sellerId) {
            return redirect()->to('/login');
        }

        $saleEvent = $this->saleEventModel->find($saleEventId);
        if (!$saleEvent) {
            return redirect()->to('/');
        }
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if (!$listing || $listing['seller_party_id'] !== $sellerId) {
            return service('response')->setStatusCode(403)
                ->setBody('BR-42: only the listing\'s seller may accept an offer on it.');
        }

        $reason = $this->request->getPost('reason') ?: null;

        try {
            $this->offers->acceptOffer($saleEventId, $offerId, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$listing['id']}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$listing['id']}");
    }
}
