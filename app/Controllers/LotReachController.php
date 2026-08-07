<?php

namespace App\Controllers;

use App\Libraries\ListingReachService;

// D-105: "Lot Reach & Interest" — a Market Maker's (seller's) reach
// dashboard across their live listings, and the real bulk-message
// action to the buyers matched against a specific one.
class LotReachController extends BaseController
{
    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function index()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $summary = (new ListingReachService())->getReachSummary($partyId);
        return view('reach/index', ['title' => 'Lot Reach & Interest — AdwitiX', 'summary' => $summary]);
    }

    public function sendMessage(string $listingId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        try {
            (new ListingReachService())->sendBulkMessage($listingId, $partyId, (string) $this->request->getPost('message_body'));
            session()->setFlashdata('success', 'Message sent.');
        } catch (\RuntimeException $e) {
            session()->setFlashdata('error', $e->getMessage());
        }
        return redirect()->to('/my-listings/reach');
    }
}
