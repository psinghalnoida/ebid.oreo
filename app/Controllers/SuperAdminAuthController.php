<?php

namespace App\Controllers;

use App\Libraries\SuperAdminAuthService;

class SuperAdminAuthController extends BaseController
{
    private SuperAdminAuthService $auth;

    public function __construct()
    {
        $this->auth = new SuperAdminAuthService();
    }

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // Setup requires being logged in normally first (proves you control
    // the account the role was granted to) — this only enrolls 2FA, it
    // does not grant the super_admin role itself (that's grant:super-admin).
    public function setupTotpForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        try {
            $setup = $this->auth->beginTotpSetup($partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to('/')->with('error', $e->getMessage());
        }

        return view('admin/setup_totp', ['title' => 'Set Up Super Admin 2FA — eBid Hub', 'setup' => $setup]);
    }

    public function setupTotpSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $code = $this->request->getPost('code');
        try {
            $confirmed = $this->auth->confirmTotpSetup($partyId, $code);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/setup-totp')->with('error', $e->getMessage());
        }

        if (!$confirmed) {
            return redirect()->to('/admin/setup-totp')->with('error', 'Invalid code — check your authenticator app and try again.');
        }

        return redirect()->to('/admin/login')->with('error', '2FA enabled successfully — log in below.');
    }

    public function loginForm()
    {
        return view('admin/login', ['title' => 'Super Admin Login — eBid Hub']);
    }

    public function loginSubmit()
    {
        $mobile = trim($this->request->getPost('mobile_number'));
        $mpin = trim($this->request->getPost('mpin'));
        $totpCode = trim($this->request->getPost('totp_code'));

        try {
            $party = $this->auth->login($mobile, $mpin, $totpCode);
        } catch (\RuntimeException $e) {
            return view('admin/login', ['title' => 'Super Admin Login — eBid Hub', 'error' => $e->getMessage()]);
        }

        // Distinct session markers from regular login — this is the real
        // separate-login-path security boundary the SuperAdminFilter checks.
        session()->set('super_admin_totp_verified_at', date('Y-m-d H:i:s'));
        session()->set('super_admin_party_id', $party['id']);
        session()->set('logged_in_party_id', $party['id']); // also usable as a regular session

        return redirect()->to('/admin');
    }

    public function logout()
    {
        session()->remove(['super_admin_totp_verified_at', 'super_admin_party_id']);
        return redirect()->to('/admin/login');
    }
}
