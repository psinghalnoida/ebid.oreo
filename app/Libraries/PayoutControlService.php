<?php

namespace App\Libraries;

use App\Models\PartyModel;
use App\Models\EmdHoldModel;
use App\Models\PayoutReleaseReviewModel;

// BR-50: Payout Account Change Control.
//
// A change to a party's registered payout bank details requires (a) OTP
// re-verification, (b) a mandatory 24-hour cooling-off before the new
// account becomes active for any payout, and (c) full before/after audit
// logging. High-value pending payouts additionally require Tenant Admin
// or SaaS Admin review before release to a newly-changed account.
//
// One bank-details field serves both buyer refunds and seller settlement
// payouts — confirmed with the project owner: a Seller is a Buyer
// upgraded on a tenant (BR-09), the same party, so one record is correct
// rather than two parallel ones.
class PayoutControlService
{
    private const COOLING_OFF_HOURS = 24; // BR-50: exact figure, stated in the text

    // BR-49/D-49's confirmed ₹10L threshold, reused here rather than a
    // second invented number, per the project owner's explicit choice.
    // PR-04/D-75: now reads the same live "BR-49.high_value_threshold"
    // Super Admin rule SettlementService reads — editing it in one place
    // genuinely changes both the disposal-reporting trigger and this
    // payout review gate together, since they're the same rule.
    private const HIGH_VALUE_THRESHOLD_DEFAULT = 1000000.0;

    // Not specified by BR-50's text (which says "a newly-changed
    // account" without stating how long "newly" means) — a reasonable
    // default, flagged the same way the settlement-stall and OTP-attempt
    // thresholds were elsewhere in this codebase, not a settled figure.
    private const RECENT_CHANGE_REVIEW_WINDOW_DAYS = 30;

    private PartyModel $partyModel;
    private EmdHoldModel $emdHoldModel;
    private PayoutReleaseReviewModel $reviewModel;

    public function __construct()
    {
        $this->partyModel = new PartyModel();
        $this->emdHoldModel = new EmdHoldModel();
        $this->reviewModel = new PayoutReleaseReviewModel();
    }

    // Step 1: OTP is sent to the account holder's own registered mobile
    // — reuses AuthService rather than a parallel OTP mechanism.
    public function requestBankChange(string $partyId, string $mobileNumber, string $accountNumber, string $ifsc): string
    {
        if (trim($accountNumber) === '' || trim($ifsc) === '') {
            throw new \RuntimeException('BR-50: both an account number and IFSC are required.');
        }
        return (new AuthService())->requestOtp($mobileNumber, 'payout_bank_change');
    }

    // Step 2: OTP verified — the new details are staged as PENDING, not
    // active. The currently-active account (if any) keeps being used for
    // any payout until the cooling-off genuinely elapses.
    public function confirmBankChange(string $partyId, string $mobileNumber, string $accountNumber, string $ifsc, string $submittedOtp): array
    {
        if (!(new AuthService())->verifyOtp($mobileNumber, 'payout_bank_change', $submittedOtp)) {
            throw new \RuntimeException('Incorrect or expired OTP.');
        }

        $party = $this->partyModel->find($partyId);
        $activatesAt = (new \DateTimeImmutable())->modify('+' . self::COOLING_OFF_HOURS . ' hours');

        $this->partyModel->update($partyId, [
            'payout_bank_pending_account_number' => $accountNumber,
            'payout_bank_pending_ifsc' => $ifsc,
            'payout_bank_pending_activates_at' => $activatesAt->format('Y-m-d H:i:s'),
        ]);

        (new AuditLogService())->log('payout_bank.change_requested', $partyId, [
            'oldAccountMasked' => $this->mask($party['payout_bank_account_number'] ?? null),
            'newAccountMasked' => $this->mask($accountNumber),
            'ifsc' => $ifsc,
            'activatesAt' => $activatesAt->format('Y-m-d H:i:s'),
        ]);

        return $this->partyModel->find($partyId);
    }

    // The 24-hour cooling-off is a time-based transition, same as every
    // other timer on this platform (grace period, Express window, offer
    // lapse) — wired into SchedulerService::runAll().
    public function promoteDuePendingChanges(): array
    {
        $due = $this->partyModel
            ->where('payout_bank_pending_activates_at IS NOT NULL')
            ->where('payout_bank_pending_activates_at <=', date('Y-m-d H:i:s'))
            ->findAll();

        $promoted = [];
        foreach ($due as $party) {
            $this->partyModel->update($party['id'], [
                'payout_bank_account_number' => $party['payout_bank_pending_account_number'],
                'payout_bank_ifsc' => $party['payout_bank_pending_ifsc'],
                'payout_bank_updated_at' => date('Y-m-d H:i:s'),
                'payout_bank_pending_account_number' => null,
                'payout_bank_pending_ifsc' => null,
                'payout_bank_pending_activates_at' => null,
            ]);
            (new AuditLogService())->log('payout_bank.change_activated', null, [
                'partyId' => $party['id'],
                'newAccountMasked' => $this->mask($party['payout_bank_pending_account_number']),
            ]);
            $promoted[] = $party['id'];
        }
        return $promoted;
    }

    // The one real choke point every plain EMD release routes through
    // (OfferService, ListingLifecycleService, TenderReviewService) —
    // replacing direct EmdHoldModel::markReleased() calls, the same
    // pattern as BiddingService::placeBid being the single choke point
    // for real-time broadcasts (D-42). Returns true if released
    // immediately, false if deferred to Tenant/SaaS Admin review.
    public function guardedRelease(string $holdId): bool
    {
        $hold = $this->emdHoldModel->find($holdId);
        if (!$hold) {
            throw new \RuntimeException('EMD hold not found.');
        }
        if ($this->needsReview($hold['party_id'], (float) $hold['amount'])) {
            if (!$this->reviewModel->hasPendingReviewForHold($holdId)) {
                $this->reviewModel->createReview($holdId, $hold['party_id'], (float) $hold['amount'], 'plain_release');
                (new AuditLogService())->log('payout.release_held_for_review', null, [
                    'holdId' => $holdId, 'partyId' => $hold['party_id'], 'amount' => (float) $hold['amount'],
                ]);
            }
            return false;
        }
        $this->emdHoldModel->markReleased($holdId);
        return true;
    }

    // Same choke-point role for SettlementService's buyer-refund path.
    public function guardedSettle(string $holdId, float $tenantAmount, float $saasAmount, float $buyerRefund): bool
    {
        $hold = $this->emdHoldModel->find($holdId);
        if (!$hold) {
            throw new \RuntimeException('EMD hold not found.');
        }
        if ($this->needsReview($hold['party_id'], $buyerRefund)) {
            if (!$this->reviewModel->hasPendingReviewForHold($holdId)) {
                $this->reviewModel->createReview($holdId, $hold['party_id'], $buyerRefund, 'settlement_refund', $tenantAmount, $saasAmount, $buyerRefund);
                (new AuditLogService())->log('payout.release_held_for_review', null, [
                    'holdId' => $holdId, 'partyId' => $hold['party_id'], 'amount' => $buyerRefund,
                ]);
            }
            return false;
        }
        $this->emdHoldModel->markSettled($holdId, $tenantAmount, $saasAmount, $buyerRefund);
        return true;
    }

    private function needsReview(string $partyId, float $amount): bool
    {
        $threshold = SovereignRuleService::getNumeric('BR-49.high_value_threshold', self::HIGH_VALUE_THRESHOLD_DEFAULT);
        if ($amount < $threshold) {
            return false;
        }
        $party = $this->partyModel->find($partyId);
        if (empty($party['payout_bank_updated_at'])) {
            return false; // never changed — nothing recent to protect against
        }
        $cutoff = (new \DateTimeImmutable($party['payout_bank_updated_at']))->modify('+' . self::RECENT_CHANGE_REVIEW_WINDOW_DAYS . ' days');
        return new \DateTimeImmutable() <= $cutoff;
    }

    // BR-50: Tenant Admin (for the hold's own tenant) or SaaS Admin —
    // checked by the caller (PayoutReviewController), which resolves the
    // right identity from whichever session is active.
    public function decideReview(string $reviewId, string $reviewerPartyId, bool $approve, string $rationale): array
    {
        $review = $this->reviewModel->find($reviewId);
        if (!$review) {
            throw new \RuntimeException('Payout review not found.');
        }
        if ($review['status'] !== 'pending') {
            throw new \RuntimeException('This payout review has already been decided.');
        }

        if ($approve) {
            if ($review['release_type'] === 'settlement_refund') {
                $this->emdHoldModel->markSettled($review['emd_hold_id'], (float) $review['tenant_amount'], (float) $review['saas_amount'], (float) $review['buyer_refund']);
            } else {
                $this->emdHoldModel->markReleased($review['emd_hold_id']);
            }
        }

        $this->reviewModel->markDecision($reviewId, $reviewerPartyId, $approve ? 'approved' : 'declined', $rationale);

        (new AuditLogService())->log('payout.release_review_decided', $reviewerPartyId, [
            'reviewId' => $reviewId, 'holdId' => $review['emd_hold_id'], 'partyId' => $review['party_id'],
            'amount' => (float) $review['amount'], 'approved' => $approve, 'rationale' => $rationale,
        ]);

        return $this->reviewModel->find($reviewId);
    }

    // Full account numbers ARE stored on the party record (needed for
    // any real future payout) — but audit-log payloads only ever carry
    // the masked form, same discretion this codebase already applies to
    // aadhaar_masked, so a compromised log export doesn't itself leak a
    // usable account number.
    private function mask(?string $accountNumber): ?string
    {
        if ($accountNumber === null || strlen($accountNumber) <= 4) {
            return $accountNumber;
        }
        return str_repeat('X', strlen($accountNumber) - 4) . substr($accountNumber, -4);
    }
}
