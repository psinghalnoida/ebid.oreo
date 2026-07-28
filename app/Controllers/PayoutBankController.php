<?php

namespace App\Controllers;

use App\Libraries\PayoutControlService;
use App\Models\PartyModel;

// BR-50: a party requesting to change their own payout bank details —
// OTP re-verification, then a 24-hour cooling-off before it takes effect.
class PayoutBankController extends BaseController
{
    public function requestForm()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $party = (new PartyModel())->find($partyId);
        return view('payout_bank/request', ['title' => 'Payout Bank Details — eBid Hub', 'party' => $party]);
    }

    public function requestSubmit()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $party = (new PartyModel())->find($partyId);
        $accountNumber = trim($this->request->getPost('account_number'));
        $ifsc = trim($this->request->getPost('ifsc'));

        try {
            $otp = (new PayoutControlService())->requestBankChange($partyId, $party['mobile_number'], $accountNumber, $ifsc);
        } catch (\RuntimeException $e) {
            return view('payout_bank/request', ['title' => 'Payout Bank Details — eBid Hub', 'party' => $party, 'error' => $e->getMessage()]);
        }

        session()->set('pending_payout_bank_change', ['account_number' => $accountNumber, 'ifsc' => $ifsc]);

        // Dev-only convenience: OTP shown on-screen since the SMS
        // provider is stubbed, same pattern as every other OTP flow on
        // this platform.
        return view('payout_bank/confirm', ['title' => 'Confirm Bank Change — eBid Hub', 'devOtp' => $otp]);
    }

    public function confirmSubmit()
    {
        $partyId = session()->get('logged_in_party_id');
        if (!$partyId) return redirect()->to('/login');

        $pending = session()->get('pending_payout_bank_change');
        if (!$pending) {
            return redirect()->to('/payout-bank');
        }

        $party = (new PartyModel())->find($partyId);
        $otp = trim($this->request->getPost('otp'));

        try {
            (new PayoutControlService())->confirmBankChange(
                $partyId, $party['mobile_number'], $pending['account_number'], $pending['ifsc'], $otp
            );
        } catch (\RuntimeException $e) {
            return view('payout_bank/confirm', ['title' => 'Confirm Bank Change — eBid Hub', 'error' => $e->getMessage()]);
        }

        session()->remove('pending_payout_bank_change');
        return redirect()->to('/payout-bank')->with('error', 'Bank details updated — active in 24 hours (BR-50 cooling-off). Your current details keep being used for any payout until then.');
    }
}
