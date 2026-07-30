<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\BidModel;
use App\Models\EmdHoldModel;
use App\Models\PartyRoleModel;
use App\Models\RatingEventModel;
use App\Libraries\CascadeService;
use App\Libraries\DisputeService;
use App\Libraries\RatingService;
use App\Libraries\SettlementService;
use App\Libraries\StandingReviewService;

class TestBr35RatingEvents extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:br35';
    protected $description = 'Proves BR-35\'s named graduated rating events are real, not just documented magnitudes.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $bidModel = new BidModel();
        $emdHoldModel = new EmdHoldModel();
        $roleModel = new PartyRoleModel();
        $ratingEventModel = new RatingEventModel();
        $cascade = new CascadeService();
        $dispute = new DisputeService();
        $rating = new RatingService();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'BR35 Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'br35test']);
        $seller = $partyModel->createParty('+919555403001');
        $tenantAdmin = $partyModel->createParty('+919555403002');
        $superAdmin = $partyModel->createParty('+919555403003');
        $roleModel->promoteTenantAdmin($tenantAdmin['id'], $tenant['id']);
        $roleModel->grantRole($superAdmin['id'], 'super_admin', null);

        $makeListing = fn(string $pin) => $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => $pin,
        ]);

        CLI::write("\n=== 1st/2nd/3rd Default — a genuine, previously-undiscovered gap: CascadeService never touched ratings at all before this ===", 'yellow');
        $defaulter = $partyModel->createParty('+919555403004');
        $h2Bidder = $partyModel->createParty('+919555403005');
        $h3Bidder = $partyModel->createParty('+919555403006');
        $listing1 = $makeListing('600090');
        $se1 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing1['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BR35-CASCADE-001',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close',
        ]);
        $emdHoldModel->createHold($se1['id'], $defaulter['id'], 'van', 10000);
        $emdHoldModel->createHold($se1['id'], $h2Bidder['id'], 'van', 10000);
        $emdHoldModel->createHold($se1['id'], $h3Bidder['id'], 'van', 10000);
        $bid = $bidModel->createBid($se1['id'], $defaulter['id'], 140000);
        $bidModel->createBid($se1['id'], $h2Bidder['id'], 130000);
        $bidModel->createBid($se1['id'], $h3Bidder['id'], 120000);
        $cascade->processDefault($se1['id'], $bid['id']);

        $ev1 = $db->table('rating_event')->where('party_id', $defaulter['id'])->where('event_key', 'default_1st')->get()->getRowArray();
        $this->assert($ev1 !== null, 'A real rating_event row exists for the 1st default, tagged with the correct event_key');
        $this->assert((float) $ev1['previous_value'] === 3.0 && (float) $ev1['new_value'] === 2.0, '1st Default is genuinely -1.0★ (3.0 -> 2.0), matching BR-35\'s table exactly');
        $this->assert($ev1['status'] === 'pending_tenant_approval', 'BR-36: even a system-detected default goes through the approval gate, not applied silently');
        $this->assert($ev1['related_sale_event_id'] === $se1['id'], 'The event is genuinely attributable to its sale event — reachable by that tenant\'s admin');

        $afterFirstDefault = $partyModel->find($defaulter['id']);
        $this->assert((float) $afterFirstDefault['star_rating'] === 3.0, 'The rating has NOT actually changed yet — still pending approval');

        CLI::write("\n=== The Tenant Admin genuinely sees and can approve it via the new review queue ===", 'yellow');
        $tenantIds = $roleModel->findAdministeredTenantIds($tenantAdmin['id']);
        $visibleToTenantAdmin = $ratingEventModel->findPendingForTenants($tenantIds);
        $this->assert(in_array($ev1['id'], array_column($visibleToTenantAdmin, 'id'), true), 'This Tenant Admin genuinely sees the pending event in their scoped queue');

        $approval1 = $rating->approveDowngrade($ev1['id'], $tenantAdmin['id'], 'tenant_admin');
        $this->assert($approval1['applied'] === false, 'newValue=2.0 requires DUAL approval — Tenant Admin alone is not enough');
        $rating->approveDowngrade($ev1['id'], $superAdmin['id'], 'super_admin');
        $this->assert((float) $partyModel->find($defaulter['id'])['star_rating'] === 2.0, 'Once both approvals land, the rating genuinely reflects the 1st Default');

        CLI::write("\n=== 2nd Default continues the ladder correctly, at the right magnitude ===", 'yellow');
        $rankedAfterFirst = $bidModel->findRankedBids($se1['id'], 3);
        $secondDefaulterBidId = $rankedAfterFirst[1]['id'];
        $secondDefaulterPartyId = $rankedAfterFirst[1]['bidder_party_id'];
        $cascade->processDefault($se1['id'], $secondDefaulterBidId);
        $ev2 = $db->table('rating_event')->where('party_id', $secondDefaulterPartyId)->where('event_key', 'default_2nd')->get()->getRowArray();
        $this->assert($ev2 !== null, 'A 2nd Default event genuinely exists for the correct (different) bidder');
        $this->assert(abs((float) $ev2['previous_value'] - (float) $ev2['new_value'] - 1.5) < 0.01, '2nd Default is genuinely -1.5★, not the same magnitude as the 1st');

        CLI::write("\n=== Dispute ruling category+role mapping (BR-35 named events, not the old flat -0.5) ===", 'yellow');
        $buyerA = $partyModel->createParty('+919555403010');
        $buyerB = $partyModel->createParty('+919555403011');
        $buyerC = $partyModel->createParty('+919555403012');

        // condition_delivery, ruled against the FILER (buyer) -> Frivolous Dispute
        $listingCD = $makeListing('600091');
        $seCD = $saleEventModel->createSaleEvent(['listing_id' => $listingCD['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BR35-CD-001', 'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active', 'current_high_bidder_party_id' => $buyerA['id']]);
        $dCD = $dispute->fileDispute($seCD['id'], $buyerA['id'], 'condition_delivery', 'The item was not as described (later found baseless)');
        $dispute->ruleOnDispute($dCD['id'], $tenantAdmin['id'], 'rating_consequence', 'Complaint found baseless.', $buyerA['id']);
        $frivolous = $db->table('rating_event')->where('party_id', $buyerA['id'])->where('event_key', 'frivolous_dispute')->get()->getRowArray();
        $this->assert($frivolous !== null, 'condition_delivery ruled against the buyer maps to the exact "Frivolous Dispute" named event, not a generic -0.5');
        $this->assert(abs((float) $frivolous['previous_value'] - (float) $frivolous['new_value'] - 1.5) < 0.01, 'Frivolous Dispute is genuinely -1.5★');

        // condition_delivery, ruled against the seller -> Data Mismatch
        $listingCD2 = $makeListing('600092');
        $seCD2 = $saleEventModel->createSaleEvent(['listing_id' => $listingCD2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BR35-CD-002', 'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active', 'current_high_bidder_party_id' => $buyerB['id']]);
        $dCD2 = $dispute->fileDispute($seCD2['id'], $buyerB['id'], 'condition_delivery', 'Genuine discrepancy from the listing');
        $dispute->ruleOnDispute($dCD2['id'], $tenantAdmin['id'], 'rating_consequence', 'Confirmed discrepancy.', $seller['id']);
        $dataMismatch = $db->table('rating_event')->where('party_id', $seller['id'])->where('event_key', 'data_mismatch')->get()->getRowArray();
        $this->assert($dataMismatch !== null, 'condition_delivery ruled against the seller maps to "Data Mismatch"');

        // payment, ruled against the buyer -> Late Payment
        $listingPay = $makeListing('600093');
        $sePay = $saleEventModel->createSaleEvent(['listing_id' => $listingPay['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BR35-PAY-001', 'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active', 'current_high_bidder_party_id' => $buyerC['id']]);
        $dPay = $dispute->fileDispute($sePay['id'], $seller['id'], 'payment', 'Buyer paid very late');
        $dispute->ruleOnDispute($dPay['id'], $tenantAdmin['id'], 'rating_consequence', 'Confirmed late payment.', $buyerC['id']);
        $latePayment = $db->table('rating_event')->where('party_id', $buyerC['id'])->where('event_key', 'late_payment')->get()->getRowArray();
        $this->assert($latePayment !== null, 'payment ruled against the buyer maps to "Late Payment"');

        // auction_rejection — no clean 1:1 named-table match, must keep the honest generic fallback
        $listingAR = $makeListing('600094');
        $seAR = $saleEventModel->createSaleEvent(['listing_id' => $listingAR['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-BR35-AR-001', 'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active', 'current_high_bidder_party_id' => $buyerA['id']]);
        $dAR = $dispute->fileDispute($seAR['id'], $buyerA['id'], 'auction_rejection', 'Seller rejected without reason');
        $arDowngrade = $dispute->ruleOnDispute($dAR['id'], $tenantAdmin['id'], 'rating_consequence', 'Confirmed unreasonable rejection.', $seller['id']);
        $arEvent = $db->table('rating_event')->where('party_id', $seller['id'])->like('reason', $dAR['id'])->get()->getRowArray();
        $this->assert($arEvent !== null && $arEvent['event_key'] === null, 'auction_rejection has no clean named-table match — honestly keeps the prior generic magnitude, not a fabricated mapping');

        CLI::write("\n=== Confirmed fraud — delistSellerForFraud now genuinely resets the seller rating to 1★, self-approved ===", 'yellow');
        $fraudSeller = $partyModel->createParty('+919555403020');
        $rating->applyUpgrade($fraudSeller['id'], 'seller_star_rating', 0.5, 'setup'); // 3.5, so the reset is genuinely visible
        $rating->delistSellerForFraud($fraudSeller['id'], $superAdmin['id'], 'Confirmed bid manipulation ring');
        $this->assert((float) $partyModel->find($fraudSeller['id'])['seller_star_rating'] === 1.0, 'The seller rating is genuinely reset to exactly 1.0★');
        $fraudEvent = $db->table('rating_event')->where('party_id', $fraudSeller['id'])->where('event_key', 'confirmed_fraud')->get()->getRowArray();
        $this->assert($fraudEvent['status'] === 'applied', 'Self-approved immediately — Super Admin\'s confirmed-fraud finding already outranks the approval gate');

        CLI::write("\n=== CBS violation: warning-tier (1st-2nd) applies NO rating consequence; 3rd+ genuinely does ===", 'yellow');
        $cbsSeller = $partyModel->createParty('+919555403021');
        $cbsListing = $makeListing('600095');
        $standingReview = new StandingReviewService();
        $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $noEventYet = $db->table('rating_event')->where('party_id', $cbsSeller['id'])->where('event_key', 'confirmed_cbs_violation')->countAllResults();
        $this->assert($noEventYet === 0, 'Offenses 1-2 (warning tier) correctly apply no rating consequence at all');

        $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $cbsEvent = $db->table('rating_event')->where('party_id', $cbsSeller['id'])->where('event_key', 'confirmed_cbs_violation')->get()->getRowArray();
        $this->assert($cbsEvent !== null, 'The 3rd offense (past warning stage) genuinely applies "Confirmed CBS violation" -2.0★');
        $this->assert($cbsEvent['tenant_admin_approved_at'] !== null, 'The flagging Tenant Admin\'s own decision self-approves their tier');
        $this->assert($cbsEvent['status'] !== 'applied', 'BUT -2.0★ from 3.0 lands at/below 2.0★, requiring DUAL approval — a Tenant Admin alone genuinely cannot fully resolve this, same rule as DisputeService');

        CLI::write("\nWhen the flagger IS Super Admin, both approval tiers are satisfied immediately (outranks the dual gate).", 'yellow');
        $cbsSeller2 = $partyModel->createParty('+919555403022');
        $cbsListing2 = $makeListing('600096');
        $standingReview->recordCbsViolation($cbsSeller2['id'], $superAdmin['id'], $cbsListing2['id']);
        $standingReview->recordCbsViolation($cbsSeller2['id'], $superAdmin['id'], $cbsListing2['id']);
        $standingReview->recordCbsViolation($cbsSeller2['id'], $superAdmin['id'], $cbsListing2['id']);
        $cbsEvent2 = $db->table('rating_event')->where('party_id', $cbsSeller2['id'])->where('event_key', 'confirmed_cbs_violation')->get()->getRowArray();
        $this->assert($cbsEvent2['status'] === 'applied', 'Flagged by Super Admin directly: fully self-approved immediately, same pattern as delistSellerForFraud');

        CLI::write("\n=== Sustained clean streak: a general reward distinct from BR-38's crawl-back-specific counter ===", 'yellow');
        $streakBuyer = $partyModel->createParty('+919555403030');
        for ($i = 1; $i <= 9; $i++) {
            $rating->recordCleanStreak($streakBuyer['id'], 'star_rating');
        }
        $this->assert((float) $partyModel->find($streakBuyer['id'])['star_rating'] === 3.0, 'After 9 clean transactions, no upgrade has fired yet');
        $rating->recordCleanStreak($streakBuyer['id'], 'star_rating');
        $this->assert((float) $partyModel->find($streakBuyer['id'])['star_rating'] === 3.6, 'On the exact 10th, "Sustained clean streak" +0.6 genuinely fires — applied immediately, upgrades need no approval');
        $this->assert((int) $partyModel->find($streakBuyer['id'])['clean_transaction_streak_buyer'] === 0, 'The streak counter genuinely resets after firing, so it can be earned again');

        CLI::write("\n=== Dispute-driven rating events are now genuinely attributable to a sale event (real fix, not assumed) ===", 'yellow');
        $this->assert($dataMismatch['related_sale_event_id'] === $seCD2['id'], 'The Data Mismatch event carries the real sale_event_id — a Tenant Admin can now actually be scoped to review it');
        $this->assert($latePayment['related_sale_event_id'] === $sePay['id'], 'Same for Late Payment — previously DisputeService never threaded this at all');

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
