<?php

namespace App\Controllers;

use App\Libraries\PayoutControlService;
use App\Libraries\UserAuthContext;
use App\Models\PartyModel;

// BR-50: a party requesting to change their own payout bank details —
// OTP re-verification, then a 24-hour cooling-off before it takes effect.
// The "pending change" step is now a stateless JWT ticket (like
// UserAuthApiService's otp_ticket) instead of a PHP session value, so it
// survives a stateless REST client with no cookie jar.
class PayoutBankController extends BaseController
{
    private const PENDING_TICKET_TYP = 'payout_bank_change_pending';

    public function requestSubmit()
    {
        $partyId = UserAuthContext::partyId();
        $party = (new PartyModel())->find($partyId);
        $accountNumber = trim((string) $this->input('account_number'));
        $ifsc = trim((string) $this->input('ifsc'));

        try {
            $otp = (new PayoutControlService())->requestBankChange($partyId, $party['mobile_number'], $accountNumber, $ifsc);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'request_failed', $e->getMessage());
        }

        $pendingTicket = \App\Libraries\UserAuthApiService::issuePendingTicket(self::PENDING_TICKET_TYP, [
            'sub' => $partyId, 'account_number' => $accountNumber, 'ifsc' => $ifsc,
        ]);

        // Dev-only convenience: OTP shown on-screen since the SMS
        // provider is stubbed, same pattern as every other OTP flow on
        // this platform.
        return $this->apiResponse(['pending_ticket' => $pendingTicket, 'dev_otp' => $otp]);
    }

    public function confirmSubmit()
    {
        $partyId = UserAuthContext::partyId();
        $pending = \App\Libraries\UserAuthApiService::decodePendingTicket((string) $this->input('pending_ticket'), self::PENDING_TICKET_TYP);
        if (!$pending || $pending['sub'] !== $partyId) {
            return $this->jsonError(401, 'invalid_ticket', 'Invalid or expired pending_ticket — start the bank change request again.');
        }

        $party = (new PartyModel())->find($partyId);
        $otp = trim((string) $this->input('otp'));

        try {
            (new PayoutControlService())->confirmBankChange(
                $partyId, $party['mobile_number'], $pending['account_number'], $pending['ifsc'], $otp
            );
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'confirm_failed', $e->getMessage());
        }

        return $this->apiResponse([
            'message' => 'Bank details updated — active in 24 hours (BR-50 cooling-off). Your current details keep being used for any payout until then.',
        ]);
    }
}
