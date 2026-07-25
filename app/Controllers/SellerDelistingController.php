<?php

namespace App\Controllers;

use App\Libraries\RatingService;
use App\Models\PartyModel;

class SellerDelistingController extends BaseController
{
    public function form()
    {
        return view('admin/delist_seller', ['title' => 'Delist Seller — eBid Hub']);
    }

    public function submit()
    {
        $superAdminId = session()->get('logged_in_party_id');
        $mobile = $this->request->getPost('mobile_number');
        $reason = $this->request->getPost('confirmed_fraud_reason');

        if (!$mobile || !$reason) {
            return redirect()->back()->with('error', 'Both the seller\'s mobile number and a confirmed-fraud reason are required.');
        }

        $party = (new PartyModel())->findByMobile($mobile);
        if (!$party) {
            return redirect()->back()->with('error', 'No registered party found with that mobile number.');
        }

        try {
            $result = (new RatingService())->delistSellerForFraud($party['id'], $superAdminId, $reason);
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin')->with('error',
            "Seller delisted. {$result['listingsSuspended']} active listing(s) suspended across every tenant."
        );
    }
}
