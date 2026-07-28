<?php

namespace App\Controllers;

use App\Libraries\AuthService;
use App\Libraries\PayoutAccountService;
use App\Models\PartyModel;

// BR-50/PR-28: change flow mirrors AuthController's registration pattern
// exactly — submit details, OTP sent, OTP verified, then (and only then)
// committed. The new account details sit in session between steps, not
// the database, until OTP verification actually succeeds.
class PayoutAccountController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    public function changeForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        return view('payout_account/change', ['title' => 'Change Payout Bank Account — eBid Hub']);
    }

    public function changeSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $accountHolderName = trim((string) $this->request->getPost('account_holder_name'));
        $accountNumber = trim((string) $this->request->getPost('account_number'));
        $ifscCode = trim((string) $this->request->getPost('ifsc_code'));

        if ($accountHolderName === '' || $accountNumber === '' || $ifscCode === '') {
            return view('payout_account/change', [
                'title' => 'Change Payout Bank Account — eBid Hub',
                'error' => 'All fields are required.',
            ]);
        }

        $party = (new PartyModel())->find($partyId);

        try {
            $otp = $this->auth->requestOtp($party['mobile_number'], 'bank_account_change');
        } catch (\RuntimeException $e) {
            return view('payout_account/change', ['title' => 'Change Payout Bank Account — eBid Hub', 'error' => $e->getMessage()]);
        }

        session()->set('pending_bank_account_change', [
            'account_holder_name' => $accountHolderName, 'account_number' => $accountNumber, 'ifsc_code' => $ifscCode,
        ]);

        // Dev-only convenience: OTP shown on-screen since the SMS provider
        // is stubbed — same as every other OTP step in this app.
        return view('payout_account/verify_otp', [
            'title' => 'Verify OTP — eBid Hub', 'devOtp' => $otp,
        ]);
    }

    public function changeVerifyOtpSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $pending = session()->get('pending_bank_account_change');
        if (!$pending) {
            return redirect()->to('/payout-account/change');
        }

        $party = (new PartyModel())->find($partyId);
        $otp = trim((string) $this->request->getPost('otp'));

        if (!$this->auth->verifyOtp($party['mobile_number'], 'bank_account_change', $otp)) {
            return view('payout_account/verify_otp', [
                'title' => 'Verify OTP — eBid Hub',
                'error' => 'Incorrect or expired OTP. Please try again.',
            ]);
        }

        try {
            (new PayoutAccountService())->commitChange(
                $partyId, $pending['account_holder_name'], $pending['account_number'], $pending['ifsc_code'], $partyId
            );
        } catch (\RuntimeException $e) {
            session()->remove('pending_bank_account_change');
            return redirect()->to('/profile')->with('error', $e->getMessage());
        }

        session()->remove('pending_bank_account_change');
        return redirect()->to('/profile')->with('error', 'Payout bank account updated — a 24-hour cooling-off period applies before it is active for payouts (BR-50).');
    }
}
