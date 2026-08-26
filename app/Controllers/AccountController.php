<?php

namespace App\Controllers;

use App\Libraries\AuthService;
use App\Libraries\AuditLogService;
use App\Libraries\UserAuthApiService;
use App\Libraries\UserAuthContext;
use App\Models\PartyModel;

class AccountController extends BaseController
{
    private const MPIN_CHANGE_TICKET_TYP = 'account_mpin_change_pending';

    // Phase 3A: account edit. Deliberately scoped to non-verification-
    // critical fields only — full_name, recovery_email, occupation, and
    // (for organizations) the descriptive business fields. PAN, Aadhaar,
    // CIN, GSTIN, MSME/UDYAM registration, and date of birth are NOT
    // editable here — those are BR-17's KYC-verification anchors and
    // should only change through a real KYC re-verification flow (not
    // built yet), not a casual self-service form.
    public function editSubmit()
    {
        $partyId = UserAuthContext::partyId();
        $partyModel = new PartyModel();
        $party = $partyModel->find($partyId);

        $update = [
            'full_name' => $this->input('full_name'),
            'recovery_email' => $this->input('recovery_email') ?: null,
            'occupation' => $this->input('occupation') ?: null,
        ];
        if ($party['entity_type'] === 'organization') {
            $update['org_company_type'] = $this->input('org_company_type') ?: null;
            $update['org_industry'] = $this->input('org_industry') ?: null;
            $update['org_annual_turnover'] = $this->input('org_annual_turnover') !== null && $this->input('org_annual_turnover') !== '' ? (float) $this->input('org_annual_turnover') : null;
            $update['org_employee_count'] = $this->input('org_employee_count') !== null && $this->input('org_employee_count') !== '' ? (int) $this->input('org_employee_count') : null;
        }

        $partyModel->update($partyId, $update);
        (new AuditLogService())->log('account.edited', $partyId, ['fields' => array_keys($update)]);

        return $this->response->setJSON(['party' => $partyModel->find($partyId), 'message' => 'Account details updated.']);
    }

    // mPIN change — OTP-gated even though the caller is already
    // authenticated, so a leaked access token alone can't change the
    // credential without also controlling the registered mobile. The
    // "pending" step is a stateless JWT ticket (like PayoutBankController's)
    // instead of a PHP session value.
    public function changeMpinRequestOtp()
    {
        $partyId = UserAuthContext::partyId();
        $party = (new PartyModel())->find($partyId);
        $otp = (new AuthService())->requestOtp($party['mobile_number'], 'mpin_reset');

        $pendingTicket = UserAuthApiService::issuePendingTicket(self::MPIN_CHANGE_TICKET_TYP, ['sub' => $partyId]);

        return $this->response->setJSON(['pending_ticket' => $pendingTicket, 'dev_otp' => $otp]);
    }

    public function changeMpinConfirm()
    {
        $partyId = UserAuthContext::partyId();
        $pending = UserAuthApiService::decodePendingTicket((string) $this->input('pending_ticket'), self::MPIN_CHANGE_TICKET_TYP);
        if (!$pending || $pending['sub'] !== $partyId) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket — request the OTP again.');
        }

        $party = (new PartyModel())->find($partyId);
        $otp = trim((string) $this->input('otp'));
        $newMpin = trim((string) $this->input('new_mpin'));

        if (!(new AuthService())->verifyOtp($party['mobile_number'], 'mpin_reset', $otp)) {
            return $this->jsonError(401, 'invalid_otp', 'Incorrect or expired OTP.');
        }

        try {
            (new AuthService())->setMpin($partyId, $newMpin);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'mpin_change_failed', $e->getMessage());
        }

        (new AuditLogService())->log('account.mpin_changed', $partyId, []);

        return $this->response->setJSON(['message' => 'mPIN changed successfully.']);
    }

    // Account deletion — soft delete via the existing archived_at
    // mechanism, staged 30 days out (same pattern as BR-50's payout
    // cooling-off) rather than immediate, with a genuine cancellation
    // option in the meantime.
    public function deleteRequestSubmit()
    {
        $partyId = UserAuthContext::partyId();
        $reason = $this->input('reason') ?: null;
        (new PartyModel())->update($partyId, [
            'deletion_requested_at' => date('Y-m-d H:i:s'), 'deletion_reason' => $reason,
        ]);
        (new AuditLogService())->log('account.deletion_requested', $partyId, ['reason' => $reason]);

        return $this->response->setJSON(['message' => 'Deletion requested — your account will be archived in 30 days unless you cancel before then.']);
    }

    public function deleteCancelSubmit()
    {
        $partyId = UserAuthContext::partyId();
        (new PartyModel())->update($partyId, ['deletion_requested_at' => null, 'deletion_reason' => null]);
        (new AuditLogService())->log('account.deletion_cancelled', $partyId, []);

        return $this->response->setJSON(['message' => 'Deletion request cancelled.']);
    }

    // Seller earnings summary — real aggregates from completed
    // settlements, not a separate ledger.
    public function earnings()
    {
        $partyId = UserAuthContext::partyId();

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

        return $this->response->setJSON([
            'thisMonth' => $thisMonth, 'ytd' => $ytd, 'pendingCount' => $pendingCount, 'party' => $party,
        ]);
    }
}
