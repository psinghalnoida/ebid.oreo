<?php

namespace App\Libraries;

use App\Models\PartyBankAccountModel;
use App\Models\PayoutHoldModel;
use App\Models\PartyModel;

// BR-50/PR-28: Payout Account Change Control. A change to a party's
// registered payout banking details requires OTP re-verification, a
// mandatory 24-hour cooling-off period before the new account is usable
// for ANY payout, and before/after change logging to the audit trail.
// High-value payouts pending against a recently-changed account
// additionally require explicit Tenant Admin or SaaS Admin release.
//
// The OTP re-verification step itself lives in the controller (mirrors
// AuthController's registration pattern: submit details -> OTP sent ->
// OTP verified -> commit) since AuthService::requestOtp/verifyOtp are
// already the right, existing primitive for that — this service owns
// everything from the moment OTP verification has already succeeded.
class PayoutAccountService
{
    private const COOLING_OFF_HOURS = 24; // BR-50's own text: "a mandatory 24-hour cooling-off period"

    // BR-49's exact figure (see SettlementService::HIGH_VALUE_DISPOSAL_THRESHOLD).
    // Duplicated rather than shared from there — extracting a genuinely
    // shared constant would mean refactoring SettlementService's private
    // const into something both classes import, a larger change than this
    // feature needs; flagged here so the duplication is visible, not silent.
    private const HIGH_VALUE_PAYOUT_THRESHOLD = 1000000.0;

    private PartyBankAccountModel $accountModel;
    private PayoutHoldModel $holdModel;
    private PartyModel $partyModel;

    public function __construct()
    {
        $this->accountModel = new PartyBankAccountModel();
        $this->holdModel = new PayoutHoldModel();
        $this->partyModel = new PartyModel();
    }

    // Call only after AuthService::verifyOtp() has already succeeded for
    // purpose='bank_account_change'. Supersedes whatever account was
    // current, starts a fresh 24h cooling-off clock, and logs the
    // before/after change to the immutable audit trail per BR-50(c).
    public function commitChange(string $partyId, string $accountHolderName, string $accountNumber, string $ifscCode, string $initiatingPartyId): array
    {
        if (trim($accountHolderName) === '' || trim($accountNumber) === '') {
            throw new \RuntimeException('Account holder name and account number are required.');
        }
        if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper($ifscCode))) {
            throw new \RuntimeException('Invalid IFSC code format.');
        }

        $previous = $this->accountModel->findCurrentForParty($partyId);
        $this->accountModel->supersedeCurrent($partyId);

        $activatesAt = (new \DateTimeImmutable())->modify('+' . self::COOLING_OFF_HOURS . ' hours');
        $account = $this->accountModel->createAccount([
            'party_id' => $partyId,
            'account_holder_name' => $accountHolderName,
            'account_number' => $accountNumber,
            'ifsc_code' => strtoupper($ifscCode),
            'status' => 'cooling_off',
            'activates_at' => $activatesAt->format('Y-m-d H:i:s'),
            'initiated_by_party_id' => $initiatingPartyId,
        ]);

        // BR-50(c): "logged with before/after values, timestamp, and
        // initiating party." Account numbers are masked in the audit
        // payload itself (last 4 digits only) — the full values live in
        // party_bank_account, not duplicated into the audit trail, which
        // is a different sensitivity boundary (broader admin readership).
        (new AuditLogService())->log('payout_account.changed', $initiatingPartyId, [
            'partyId' => $partyId,
            'previousAccount' => $previous ? self::maskAccountForAudit($previous) : null,
            'newAccount' => self::maskAccountForAudit($account),
            'coolingOffUntil' => $account['activates_at'],
        ]);

        return $account;
    }

    // BR-50(b)/(c): the gate every fund release must pass through.
    // $disposalValue is BR-49's own high-value measure (a settlement's
    // final_price) — NOT the literal cash amount of this specific payout,
    // which for an EMD refund is always a small fraction of it. Passing
    // the refund amount here would make the high-value branch effectively
    // unreachable (see SettlementService::attemptFundReleaseAndFinalize).
    // Returns ['allowed' => bool, 'reason' => null|'cooling_off'|'high_value_review', 'bankAccountId' => ?string].
    public function evaluatePayout(string $partyId, float $disposalValue): array
    {
        $account = $this->accountModel->findCurrentForParty($partyId);
        if (!$account) {
            // No bank account ever registered — nothing to gate. Matches
            // today's fully-simulated flow, where no payout has ever named
            // a destination account at all.
            return ['allowed' => true, 'reason' => null, 'bankAccountId' => null];
        }

        $stillCoolingOff = $account['status'] === 'cooling_off'
            && new \DateTimeImmutable() < new \DateTimeImmutable($account['activates_at']);
        if ($stillCoolingOff) {
            return ['allowed' => false, 'reason' => 'cooling_off', 'bankAccountId' => $account['id']];
        }

        if ($disposalValue > self::HIGH_VALUE_PAYOUT_THRESHOLD && !$this->holdModel->hasEverBeenReleased($account['id'])) {
            return ['allowed' => false, 'reason' => 'high_value_review', 'bankAccountId' => $account['id']];
        }

        return ['allowed' => true, 'reason' => null, 'bankAccountId' => $account['id']];
    }

    // Called by SettlementService when evaluatePayout() blocked release
    // for reason='high_value_review' — creates the reviewable hold if one
    // doesn't already exist for this exact settlement+account pair.
    public function ensureHold(string $settlementId, string $partyId, string $bankAccountId, float $amount): array
    {
        // Checks for ANY existing hold on this settlement+account, not
        // just a 'pending' one — a 'rejected' hold is a genuine terminal
        // state needing manual follow-up, not something the scheduler's
        // every-minute retry should keep spawning fresh duplicates of.
        // Confirmed as a real bug during testing: rejecting a hold, then
        // running the scheduler again, produced a second 'pending' hold
        // for the same settlement before this check was added.
        $existing = $this->holdModel->where('settlement_id', $settlementId)
            ->where('bank_account_id', $bankAccountId)->first();
        if ($existing) {
            return $existing;
        }
        return $this->holdModel->createHold([
            'settlement_id' => $settlementId, 'party_id' => $partyId,
            'bank_account_id' => $bankAccountId, 'amount' => $amount, 'status' => 'pending',
        ]);
    }

    // BR-50(c): Tenant Admin (for this hold's own tenant, via the
    // settlement) or SaaS Admin may decide. Authorization is checked by
    // the caller (SettlementController/PayoutHoldAdminController), not
    // here — this only persists the decision and logs it.
    public function reviewHold(string $holdId, string $adminPartyId, string $outcome, ?string $notes): array
    {
        if (!in_array($outcome, ['released', 'rejected'], true)) {
            throw new \RuntimeException("Invalid payout hold outcome: {$outcome}");
        }
        $hold = $this->holdModel->find($holdId);
        if (!$hold) {
            throw new \RuntimeException('Payout hold not found');
        }
        if ($hold['status'] !== 'pending') {
            throw new \RuntimeException('This payout hold has already been reviewed.');
        }

        $this->holdModel->update($holdId, [
            'status' => $outcome, 'reviewed_by_party_id' => $adminPartyId,
            'review_notes' => $notes, 'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        // BR-50/BR-05: logged regardless of outcome, same discipline as
        // BR-54's AML flag review.
        (new AuditLogService())->log('payout_hold.reviewed', $adminPartyId, [
            'holdId' => $holdId, 'settlementId' => $hold['settlement_id'], 'partyId' => $hold['party_id'],
            'amount' => $hold['amount'], 'outcome' => $outcome,
        ]);

        return $this->holdModel->find($holdId);
    }

    // BR-50/PR-28's final step: "After the cooling-off period lapses...
    // the new account becomes active." Scheduler-driven, same shape as
    // TenantMediaWaiverService::lapseExpired().
    public function activateDueAccounts(): array
    {
        $due = $this->accountModel->findDueForActivation();
        $activated = [];
        foreach ($due as $account) {
            $this->accountModel->activate($account['id']);
            $activated[] = $account['id'];
        }
        return $activated;
    }

    public function currentAccountForParty(string $partyId): ?array
    {
        return $this->accountModel->findCurrentForParty($partyId);
    }

    private static function maskAccountForAudit(array $account): array
    {
        $number = (string) $account['account_number'];
        $masked = strlen($number) > 4 ? str_repeat('X', strlen($number) - 4) . substr($number, -4) : $number;
        return [
            'accountHolderName' => $account['account_holder_name'],
            'accountNumberMasked' => $masked,
            'ifscCode' => $account['ifsc_code'],
        ];
    }
}
