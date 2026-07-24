<?php

namespace App\Controllers;

use App\Libraries\TenderService;
use App\Libraries\TenderBiddingService;
use App\Libraries\TenderReviewService;
use App\Models\SaleEventModel;
use App\Models\ListingModel;
use App\Models\PartyModel;

class TenderController extends BaseController
{
    private TenderService $tender;
    private TenderBiddingService $bidding;
    private TenderReviewService $review;
    private SaleEventModel $saleEventModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->tender = new TenderService();
        $this->bidding = new TenderBiddingService();
        $this->review = new TenderReviewService();
        $this->saleEventModel = new SaleEventModel();
        $this->listingModel = new ListingModel();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function registerInterest(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->tender->registerInterest($saleEventId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', 'Interest registered.');
    }

    public function manageEligibility(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if ($listing['seller_party_id'] !== $partyId) {
            return service('response')->setStatusCode(403)->setBody('Only the listing\'s seller may manage Tender eligibility.');
        }

        $interestModel = new \App\Models\TenderInterestModel();
        $eligibilityModel = new \App\Models\TenderEligibilityModel();

        $interested = $interestModel->findForSaleEvent($saleEventId);
        $eligible = $eligibilityModel->findForSaleEvent($saleEventId);
        $eligiblePartyIds = array_column($eligible, 'party_id');

        return view('tender/eligibility', [
            'title' => 'Manage Tender Eligibility — eBid Hub',
            'saleEvent' => $saleEvent, 'interested' => $interested, 'eligible' => $eligible,
            'eligiblePartyIds' => $eligiblePartyIds,
        ]);
    }

    public function grantEligibility(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $targetPartyId = $this->request->getPost('party_id');
        $mobile = $this->request->getPost('mobile_number');
        if ($mobile && !$targetPartyId) {
            $party = (new PartyModel())->findByMobile($mobile);
            if (!$party) {
                return redirect()->back()->with('error', 'No registered party found with that mobile number.');
            }
            $targetPartyId = $party['id'];
        }

        try {
            $this->tender->grantEligibility($saleEventId, $targetPartyId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->to("/sale-events/{$saleEventId}/tender/eligibility");
    }

    public function publishDocument(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->tender->publishDocument(
                $saleEventId, $partyId, $this->request->getPost('document_type'),
                $this->request->getPost('title'), null, $this->request->getPost('description_text')
            );
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function logEmd(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $amount = $this->request->getPost('amount') !== '' ? (float) $this->request->getPost('amount') : null;
        try {
            $this->tender->logManualEmd(
                $saleEventId, $this->request->getPost('party_id'), $amount,
                $this->request->getPost('payment_location_note') ?: null,
                $this->request->getPost('no_emd_reason') ?: null, $partyId
            );
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function placeBid(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->bidding->placeBid($saleEventId, $partyId, (float) $this->request->getPost('amount'));
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function generateStakeholderLink(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $token = $this->tender->generateStakeholderLink($saleEventId, $partyId, $this->request->getPost('label') ?: null);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', "Stakeholder link: /tender-view/{$token['token']}");
    }

    public function stakeholderView(string $token)
    {
        try {
            $saleEvent = $this->tender->resolveStakeholderToken($token);
        } catch (\RuntimeException $e) {
            return service('response')->setStatusCode(404)->setBody($e->getMessage());
        }
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        $bidModel = new \App\Models\BidModel();
        $bidAmounts = array_map(fn($b) => $b['amount'], $bidModel->where('sale_event_id', $saleEvent['id'])->orderBy('placed_at', 'ASC')->findAll());

        return view('tender/stakeholder_view', [
            'title' => 'Tender Live View — eBid Hub', 'saleEvent' => $saleEvent, 'listing' => $listing, 'bidAmounts' => $bidAmounts,
        ]);
    }

    public function closeBidding(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        try {
            $this->review->closeBiddingAndDeclareProvisional($saleEventId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function reviewAction(string $reviewId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $action = $this->request->getPost('action');
        $reason = $this->request->getPost('reason') ?: '';

        try {
            $review = $this->review->getReview($reviewId);
            $saleEvent = $this->saleEventModel->find($review['sale_event_id']);
            match ($action) {
                'extend' => $this->review->grantExtension($reviewId, $partyId, $reason),
                'reject' => $this->review->rejectAndCascade($reviewId, $partyId, $reason),
                'confirm' => $this->review->confirmWinner($reviewId, $partyId),
                default => throw new \RuntimeException('Unknown action'),
            };
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$saleEvent['listing_id']}")->with('error', $e->getMessage());
        }
        return redirect()->to("/listings/{$saleEvent['listing_id']}");
    }

    public function auctionReport(string $saleEventId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        $saleEvent = $this->saleEventModel->find($saleEventId);
        $listing = $this->listingModel->find($saleEvent['listing_id']);
        if ($listing['seller_party_id'] !== $partyId) {
            return service('response')->setStatusCode(403)->setBody('Only the listing\'s seller may view the auction report.');
        }
        $report = $this->review->generateAuctionReport($saleEventId);
        return view('tender/auction_report', ['title' => 'Auction Report — eBid Hub', 'saleEvent' => $saleEvent, 'report' => $report]);
    }
}
