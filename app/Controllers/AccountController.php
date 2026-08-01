<?php

namespace App\Controllers;

use App\Libraries\AuthService;
use App\Libraries\AuditLogService;
use App\Models\PartyModel;

class AccountController extends BaseController
{
    private function requireLogin(): ?string
    {
        return session()->get('logged_in_party_id');
    }

    // Phase 3A: account edit. Deliberately scoped to non-verification-
    // critical fields only — full_name, recovery_email, occupation, and
    // (for organizations) the descriptive business fields. PAN, Aadhaar,
    // CIN, GSTIN, MSME/UDYAM registration, and date of birth are NOT
    // editable here — those are BR-17's KYC-verification anchors and
    // should only change through a real KYC re-verification flow (not
    // built yet), not a casual self-service form.
    public function editForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $party = (new PartyModel())->find($partyId);
        return view('account/edit', ['title' => 'Edit Account — AdwitiX', 'party' => $party]);
    }

    public function editSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $partyModel = new PartyModel();
        $party = $partyModel->find($partyId);

        $update = [
            'full_name' => $this->request->getPost('full_name'),
            'recovery_email' => $this->request->getPost('recovery_email') ?: null,
            'occupation' => $this->request->getPost('occupation') ?: null,
        ];
        if ($party['entity_type'] === 'organization') {
            $update['org_company_type'] = $this->request->getPost('org_company_type') ?: null;
            $update['org_industry'] = $this->request->getPost('org_industry') ?: null;
            $update['org_annual_turnover'] = $this->request->getPost('org_annual_turnover') !== '' ? (float) $this->request->getPost('org_annual_turnover') : null;
            $update['org_employee_count'] = $this->request->getPost('org_employee_count') !== '' ? (int) $this->request->getPost('org_employee_count') : null;
        }

        $partyModel->update($partyId, $update);
        (new AuditLogService())->log('account.edited', $partyId, ['fields' => array_keys($update)]);

        return redirect()->to('/profile')->with('error', 'Account details updated.');
    }

    // mPIN change — OTP-gated even though the caller is already logged
    // in, so a hijacked session alone can't change the credential
    // without also controlling the registered mobile.
    public function changeMpinForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');
        return view('account/change_mpin_request', ['title' => 'Change mPIN — AdwitiX']);
    }

    public function changeMpinRequestOtp()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $party = (new PartyModel())->find($partyId);
        $otp = (new AuthService())->requestOtp($party['mobile_number'], 'mpin_reset');
        session()->set('pending_mpin_change_party_id', $partyId);

        return view('account/change_mpin_confirm', ['title' => 'Confirm mPIN Change — AdwitiX', 'devOtp' => $otp]);
    }

    public function changeMpinConfirm()
    {
        $partyId = session()->get('pending_mpin_change_party_id');
        if (!$partyId || $partyId !== $this->requireLogin()) {
            return redirect()->to('/account/change-mpin');
        }

        $party = (new PartyModel())->find($partyId);
        $otp = trim($this->request->getPost('otp'));
        $newMpin = trim($this->request->getPost('new_mpin'));

        if (!(new AuthService())->verifyOtp($party['mobile_number'], 'mpin_reset', $otp)) {
            return view('account/change_mpin_confirm', ['title' => 'Confirm mPIN Change — AdwitiX', 'error' => 'Incorrect or expired OTP.']);
        }

        try {
            (new AuthService())->setMpin($partyId, $newMpin);
        } catch (\RuntimeException $e) {
            return view('account/change_mpin_confirm', ['title' => 'Confirm mPIN Change — AdwitiX', 'error' => $e->getMessage()]);
        }

        session()->remove('pending_mpin_change_party_id');
        (new AuditLogService())->log('account.mpin_changed', $partyId, []);

        return redirect()->to('/profile')->with('error', 'mPIN changed successfully.');
    }

    // Account deletion — soft delete via the existing archived_at
    // mechanism, staged 30 days out (same pattern as BR-50's payout
    // cooling-off) rather than immediate, with a genuine cancellation
    // option in the meantime.
    public function deleteForm()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $party = (new PartyModel())->find($partyId);
        return view('account/delete', ['title' => 'Delete Account — AdwitiX', 'party' => $party]);
    }

    public function deleteRequestSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $reason = $this->request->getPost('reason') ?: null;
        (new PartyModel())->update($partyId, [
            'deletion_requested_at' => date('Y-m-d H:i:s'), 'deletion_reason' => $reason,
        ]);
        (new AuditLogService())->log('account.deletion_requested', $partyId, ['reason' => $reason]);

        return redirect()->to('/profile')->with('error', 'Deletion requested — your account will be archived in 30 days unless you cancel before then.');
    }

    public function deleteCancelSubmit()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        (new PartyModel())->update($partyId, ['deletion_requested_at' => null, 'deletion_reason' => null]);
        (new AuditLogService())->log('account.deletion_cancelled', $partyId, []);

        return redirect()->to('/profile')->with('error', 'Deletion request cancelled.');
    }

    // Seller earnings summary — real aggregates from completed
    // settlements, not a separate ledger.
    public function earnings()
    {
        $partyId = $this->requireLogin();
        if (!$partyId) return redirect()->to('/login');

        $db = \Config\Database::connect();
        $monthStart = date('Y-m-01 00:00:00');

        // BR-33's fee split lives on emd_hold (the buyer's hold for this
        // sale_event, released via markSettled — D-25), not on
        // settlement itself. LEFT JOIN deliberately: Tender sales use a
        // separate tender_emd_log with no platform fee deduction at all
        // (BR-56 explicitly excludes Tender), so they correctly count
        // toward sale_count but contribute nothing to total_fees. The
        // join is naturally 1:1 (BR-25: one hold per party per sale
        // event, never pooled), so no DISTINCT/dedup handling is needed.
        // BR-53: TDS lives directly on settlement.tds_amount, deducted
        // from the seller's own proceeds — distinct from, and on top of,
        // the buyer-side commission (total_fees) above.
        $earningsQuery = function (string $since) use ($db, $partyId) {
            $row = $db->table('settlement s')
                ->select('COUNT(s.id) as sale_count, COALESCE(SUM(s.final_price), 0) as total_sales,
                          COALESCE(SUM(eh.forfeited_to_tenant_amount + eh.forfeited_to_saas_amount), 0) as total_fees,
                          COALESCE(SUM(s.tds_amount), 0) as total_tds')
                ->join('emd_hold eh', 'eh.sale_event_id = s.sale_event_id AND eh.party_id = s.buyer_party_id', 'left')
                ->where('s.seller_party_id', $partyId)
                ->where('s.status', 'completed')
                ->where('s.completed_at >=', $since)
                ->get()->getRowArray();
            $row['net_earnings'] = round((float) $row['total_sales'] - (float) $row['total_fees'] - (float) $row['total_tds'], 2);
            return $row;
        };

        $thisMonth = $earningsQuery($monthStart);
        $ytd = $earningsQuery(date('Y-01-01 00:00:00'));

        $pendingCount = $db->table('settlement')
            ->where('seller_party_id', $partyId)
            ->whereIn('status', ['pending', 'stalled'])
            ->countAllResults();

        $party = (new PartyModel())->find($partyId);

        return view('account/earnings', [
            'title' => 'Earnings — AdwitiX', 'thisMonth' => $thisMonth, 'ytd' => $ytd,
            'pendingCount' => $pendingCount, 'party' => $party,
        ]);
    }
}
