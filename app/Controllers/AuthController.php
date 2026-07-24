<?php

namespace App\Controllers;

use App\Libraries\AuthService;

class AuthController extends BaseController
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    // ── Registration ─────────────────────────────────────────────

    public function registerForm(): string
    {
        return view('auth/register_mobile', ['title' => 'Register — eBid Hub']);
    }

    public function registerSubmit()
    {
        $mobile = trim($this->request->getPost('mobile_number'));

        try {
            $otp = $this->auth->requestOtp($mobile, 'registration');
        } catch (\RuntimeException $e) {
            return view('auth/register_mobile', ['title' => 'Register — eBid Hub', 'error' => $e->getMessage()]);
        }

        session()->set('pending_registration_mobile', $mobile);
        // Dev-only convenience: OTP is shown on-screen since the SMS
        // provider is stubbed (see .env SMS_PROVIDER=stub). In production
        // this would never be exposed to the browser.
        return view('auth/register_verify_otp', [
            'title' => 'Verify OTP — eBid Hub', 'mobile' => $mobile, 'devOtp' => $otp,
        ]);
    }

    public function registerVerifyOtpSubmit()
    {
        $mobile = session()->get('pending_registration_mobile');
        $otp = trim($this->request->getPost('otp'));

        if (!$mobile) {
            return redirect()->to('/register');
        }

        if (!$this->auth->verifyOtp($mobile, 'registration', $otp)) {
            return view('auth/register_verify_otp', [
                'title' => 'Verify OTP — eBid Hub', 'mobile' => $mobile,
                'error' => 'Incorrect or expired OTP. Please try again.',
            ]);
        }

        $party = $this->auth->completeRegistration($mobile);
        session()->set('pending_mpin_setup_party_id', $party['id']);
        session()->remove('pending_registration_mobile');

        return view('auth/set_mpin', ['title' => 'Set your mPIN — eBid Hub']);
    }

    public function setMpinSubmit()
    {
        $partyId = session()->get('pending_mpin_setup_party_id');
        $mpin = trim($this->request->getPost('mpin'));

        if (!$partyId) {
            return redirect()->to('/register');
        }

        try {
            $this->auth->setMpin($partyId, $mpin);
        } catch (\RuntimeException $e) {
            return view('auth/set_mpin', ['title' => 'Set your mPIN — eBid Hub', 'error' => $e->getMessage()]);
        }

        session()->remove('pending_mpin_setup_party_id');
        session()->set('logged_in_party_id', $partyId);

        return view('auth/success', ['title' => 'Welcome — eBid Hub']);
    }

    // ── Login ────────────────────────────────────────────────────

    public function loginForm(): string
    {
        return view('auth/login', ['title' => 'Log In — eBid Hub']);
    }

    public function loginSubmit()
    {
        $mobile = trim($this->request->getPost('mobile_number'));
        $mpin = trim($this->request->getPost('mpin'));

        try {
            $result = $this->auth->authenticateWithMpin($mobile, $mpin);
        } catch (\RuntimeException $e) {
            return view('auth/login', ['title' => 'Log In — eBid Hub', 'error' => $e->getMessage()]);
        }

        if ($result['status'] === 'ok') {
            session()->set('logged_in_party_id', $result['party']['id']);
            return view('auth/success', ['title' => 'Welcome back — eBid Hub']);
        }

        if ($result['status'] === 'otp_required') {
            session()->set('pending_mpin_reset_party_id', $result['partyId']);
            session()->set('pending_mpin_reset_mobile', $mobile);
            $otp = $this->auth->requestOtp($mobile, 'mpin_reset');
            return view('auth/reset_verify_otp', [
                'title' => 'Verify OTP to Reset mPIN — eBid Hub', 'mobile' => $mobile, 'devOtp' => $otp,
            ]);
        }

        return view('auth/login', [
            'title' => 'Log In — eBid Hub',
            'error' => "Incorrect mPIN. {$result['attemptsRemaining']} attempt(s) remaining before OTP verification is required.",
        ]);
    }

    public function resetVerifyOtpSubmit()
    {
        $mobile = session()->get('pending_mpin_reset_mobile');
        $partyId = session()->get('pending_mpin_reset_party_id');
        $otp = trim($this->request->getPost('otp'));

        if (!$mobile || !$partyId) {
            return redirect()->to('/login');
        }

        if (!$this->auth->verifyOtp($mobile, 'mpin_reset', $otp)) {
            return view('auth/reset_verify_otp', [
                'title' => 'Verify OTP to Reset mPIN — eBid Hub', 'mobile' => $mobile,
                'error' => 'Incorrect or expired OTP. Please try again.',
            ]);
        }

        session()->set('pending_mpin_setup_party_id', $partyId);
        session()->remove('pending_mpin_reset_mobile');
        session()->remove('pending_mpin_reset_party_id');

        return view('auth/set_mpin', ['title' => 'Set a new mPIN — eBid Hub']);
    }

    // Was missing entirely — only Super Admin had a logout route.
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/');
    }
}
