<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\PayoutReleaseReviewModel;
use App\Libraries\PayoutControlService;

class TestPayoutControl extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:payoutcontrol';
    protected $description = 'Proves BR-50 Payout Account Change Control is real: OTP + 24h cooling-off + high-value review gate.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $reviewModel = new PayoutReleaseReviewModel();
        $payout = new PayoutControlService();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Payout Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'payouttest']);
        $seller = $partyModel->createParty('+919555402101');
        $buyer = $partyModel->createParty('+919555402102');

        $makeSaleEvent = function (string $ern) use ($listingModel, $saleEventModel, $tenant, $seller) {
            $listing = $listingModel->createListing([
                'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
                'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
                'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600080',
            ]);
            return $saleEventModel->createSaleEvent([
                'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => $ern,
                'sale_format' => 'easy', 'reserve_value' => 2000000, 'result_mode' => 'instant_close',
            ]);
        };

        CLI::write("\n=== Step 1 (BR-50a): OTP re-verification is mandatory ===", 'yellow');
        $otp = $payout->requestBankChange($buyer['id'], $buyer['mobile_number'], '1234567890123', 'HDFC0001234');
        $this->assert(is_string($otp) && strlen($otp) === 6, 'A real 6-digit OTP is issued for the bank-change request');

        try {
            $payout->confirmBankChange($buyer['id'], $buyer['mobile_number'], '1234567890123', 'HDFC0001234', '000000');
            $this->assert(false, 'Should have thrown: wrong OTP');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Incorrect or expired OTP'), 'Correctly rejected: wrong OTP blocks the change');
        }

        CLI::write("\n=== Step 2 (BR-50b): the correct OTP stages the change as PENDING, not active ===", 'yellow');
        $payout->confirmBankChange($buyer['id'], $buyer['mobile_number'], '1234567890123', 'HDFC0001234', $otp);
        $afterConfirm = $partyModel->find($buyer['id']);
        $this->assert($afterConfirm['payout_bank_account_number'] === null, 'The ACTIVE account is still empty — the change has not taken effect yet');
        $this->assert($afterConfirm['payout_bank_pending_account_number'] === '1234567890123', 'The new account is genuinely staged as pending');
        $this->assert($afterConfirm['payout_bank_pending_activates_at'] !== null, 'A real 24-hour activation timestamp is set');

        $requestedLog = $db->table('audit_log')->where('event_type', 'payout_bank.change_requested')->where('actor_party_id', $buyer['id'])->get()->getRowArray();
        $this->assert($requestedLog !== null, 'BR-05: the bank-change request is genuinely logged to the immutable audit trail');

        CLI::write("\n=== Step 3 (BR-50b): the scheduler genuinely promotes it once 24 hours have passed ===", 'yellow');
        $partyModel->update($buyer['id'], ['payout_bank_pending_activates_at' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
        $promoted = $payout->promoteDuePendingChanges();
        $this->assert(in_array($buyer['id'], $promoted, true), 'The due change is genuinely promoted');
        $afterPromote = $partyModel->find($buyer['id']);
        $this->assert($afterPromote['payout_bank_account_number'] === '1234567890123', 'The ACTIVE account now genuinely matches what was staged');
        $this->assert($afterPromote['payout_bank_pending_account_number'] === null, 'The pending fields are genuinely cleared after promotion');
        $this->assert($afterPromote['payout_bank_updated_at'] !== null, 'payout_bank_updated_at is genuinely set — this is what the high-value review gate checks recency against');

        $activatedLog = $db->table('audit_log')->where('event_type', 'payout_bank.change_activated')->like('payload', $buyer['id'])->get()->getRowArray();
        $this->assert($activatedLog !== null, 'BR-05: the activation itself is genuinely logged too');

        CLI::write("\n=== Step 4: an ordinary, low-value release is completely unaffected ===", 'yellow');
        $se1 = $makeSaleEvent('TEST-PAYOUT-001');
        $ordinaryHold = $emdHoldModel->createHold($se1['id'], $buyer['id'], 'van', 5000);
        $released = $payout->guardedRelease($ordinaryHold['id']);
        $this->assert($released === true, 'A low-value release proceeds immediately — BR-50 only gates high-value payouts');
        $this->assert($emdHoldModel->find($ordinaryHold['id'])['status'] === 'released', 'The hold is genuinely released, not stuck pending review');

        CLI::write("\n=== Step 5 (BR-50c): a HIGH-VALUE release to the recently-changed account is deferred to admin review, not silently released ===", 'yellow');
        $se2 = $makeSaleEvent('TEST-PAYOUT-002');
        $highHold = $emdHoldModel->createHold($se2['id'], $buyer['id'], 'van', 1200000);
        $released = $payout->guardedRelease($highHold['id']);
        $this->assert($released === false, 'A high-value release to a recently-changed account is correctly NOT released immediately');
        $this->assert($emdHoldModel->find($highHold['id'])['status'] === 'held', 'The hold genuinely stays held, not released behind the review');
        $review = $db->table('payout_release_review')->where('emd_hold_id', $highHold['id'])->get()->getRowArray();
        $this->assert($review !== null && $review['status'] === 'pending', 'A real, pending review row exists');
        $this->assert((float) $review['amount'] === 1200000.0, 'The review records the correct amount');

        CLI::write("\nRunning guardedRelease again on the same hold does NOT create a duplicate review.", 'yellow');
        $payout->guardedRelease($highHold['id']);
        $reviewCount = $db->table('payout_release_review')->where('emd_hold_id', $highHold['id'])->countAllResults();
        $this->assert($reviewCount === 1, 'Still exactly one review, not duplicated on a repeated call');

        CLI::write("\n=== Step 6: the review window genuinely expires — a stale bank change no longer triggers review ===", 'yellow');
        $partyModel->update($buyer['id'], ['payout_bank_updated_at' => date('Y-m-d H:i:s', strtotime('-31 days'))]);
        $se3 = $makeSaleEvent('TEST-PAYOUT-003');
        $staleHold = $emdHoldModel->createHold($se3['id'], $buyer['id'], 'van', 1500000);
        $released = $payout->guardedRelease($staleHold['id']);
        $this->assert($released === true, 'Beyond the 30-day review window, a high-value release proceeds normally — the gate genuinely expires rather than protecting forever');

        CLI::write("\n=== Step 7: a party who never changed their bank details is never gated, however large the payout ===", 'yellow');
        $neverChangedParty = $partyModel->createParty('+919555402103');
        $se4 = $makeSaleEvent('TEST-PAYOUT-004');
        $neverChangedHold = $emdHoldModel->createHold($se4['id'], $neverChangedParty['id'], 'van', 5000000);
        $released = $payout->guardedRelease($neverChangedHold['id']);
        $this->assert($released === true, 'No bank change on record at all — nothing to protect against, so the payout proceeds');

        CLI::write("\n=== Step 8 (BR-50): Tenant/SaaS Admin review decision genuinely executes the deferred release ===", 'yellow');
        $tenantAdmin = $partyModel->createParty('+919555402104');
        $decided = $payout->decideReview($review['id'], $tenantAdmin['id'], true, 'Confirmed with the buyer by phone — genuine account change.');
        $this->assert($decided['status'] === 'approved', 'The review genuinely transitions to approved');
        $this->assert($emdHoldModel->find($highHold['id'])['status'] === 'released', 'Approving the review genuinely releases the hold that was withheld');

        try {
            $payout->decideReview($review['id'], $tenantAdmin['id'], true, 'Trying again.');
            $this->assert(false, 'Should have thrown: this review was already decided');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'already been decided'), 'Correctly rejected: cannot re-decide an already-decided review');
        }

        CLI::write("\n=== Step 9: a DECLINED review leaves the hold genuinely untouched — held, not released ===", 'yellow');
        $partyModel->update($buyer['id'], ['payout_bank_updated_at' => date('Y-m-d H:i:s')]);
        $se5 = $makeSaleEvent('TEST-PAYOUT-005');
        $declineHold = $emdHoldModel->createHold($se5['id'], $buyer['id'], 'van', 1300000);
        $payout->guardedRelease($declineHold['id']);
        $declineReview = $db->table('payout_release_review')->where('emd_hold_id', $declineHold['id'])->get()->getRowArray();
        $payout->decideReview($declineReview['id'], $tenantAdmin['id'], false, 'Could not reach the buyer to confirm — holding pending manual follow-up.');
        $this->assert($emdHoldModel->find($declineHold['id'])['status'] === 'held', 'A declined review genuinely leaves the EMD hold untouched, not released');

        $decidedLog = $db->table('audit_log')->where('event_type', 'payout.release_review_decided')->like('payload', $declineReview['id'])->get()->getRowArray();
        $this->assert($decidedLog !== null, 'BR-05: the review decision is genuinely logged to the immutable audit trail, regardless of outcome');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
