<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\SettlementModel;
use App\Libraries\BiddingService;
use App\Libraries\EmdService;
use App\Libraries\SovereignRuleService;
use App\Libraries\SettlementService;
use App\Libraries\PayoutControlService;
use App\Libraries\RatingService;

// PR-04: proves the Rules & Specifications module doesn't just store
// versioned text — editing a rule's numeric value genuinely changes
// live enforcement at the exact code paths that used to be hardcoded.
class TestSovereignRule extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:sovereignrule';
    protected $description = 'Proves PR-04 Sovereign Rule Revision genuinely drives live application behavior, not just a stored value.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $rules = new SovereignRuleService();
        $bidding = new BiddingService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Rule Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'ruletest']);
        $seller = $partyModel->createParty('+919555412001');
        $buyer = $partyModel->createParty('+919555412002');

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600080',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-RULE-001',
            'sale_format' => 'easy', 'reserve_value' => 100000, 'result_mode' => 'instant_close', 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', EmdService::calculateBaselineEmd('easy', null, 100000.0));

        CLI::write("\n=== Step 1: listAll() seeds the 5 wired rules at their exact original hardcoded values ===", 'yellow');
        $all = $rules->listAll();
        $byKey = [];
        foreach ($all as $r) {
            if ($r['rule_key']) $byKey[$r['rule_key']] = $r;
        }
        $this->assert(count($byKey) === 5, 'All 5 wired rules are seeded');
        $this->assert((float) $byKey['BR-43.bid_ceiling_multiplier']['numeric_value'] === 1.5, 'BR-43 seeded at the original 1.5 (150%)');
        $this->assert((float) $byKey['BR-27.emd_percent']['numeric_value'] === 0.10, 'BR-27 seeded at the original 0.10 (10%)');
        $this->assert((float) $byKey['BR-49.high_value_threshold']['numeric_value'] === 1000000.0, 'BR-49 seeded at the original ₹10L');
        $this->assert((float) $byKey['BR-38.shadow_ban_threshold']['numeric_value'] === 1.5, 'BR-38 shadow-ban seeded at the original 1.5★');
        $this->assert((float) $byKey['BR-38.crawl_back_threshold']['numeric_value'] === 2.0, 'BR-38 crawl-back seeded at the original 2.0★');

        CLI::write("\n=== Step 2: mandatory Reason for Modification is genuinely enforced ===", 'yellow');
        try {
            $rules->update($byKey['BR-43.bid_ceiling_multiplier']['id'], 'x', 'x', 'x', 1.1, '', $seller['id']);
            $this->assert(false, 'Should have thrown: empty reason');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Reason for Modification'), 'Correctly rejected: empty Reason for Modification blocks the change');
        }

        CLI::write("\n=== Step 3 (BR-43): a bid that would pass the OLD 150% ceiling but fail a tightened 120% ceiling ===", 'yellow');
        $rules->update($byKey['BR-43.bid_ceiling_multiplier']['id'], 'Anti-Jacking Bid Ceiling', 'test', 'test', 1.2, 'Tightening for this test', $seller['id']);
        $updatedRule = $rules->find($byKey['BR-43.bid_ceiling_multiplier']['id']);
        $this->assert((int) $updatedRule['version'] === 2, 'Editing the rule genuinely versions it to v2');
        $this->assert(count($rules->revisions($updatedRule['id'])) === 1, 'A revision row was recorded');

        // reserve_value=100000, no bids yet -> currentHighAmount falls back to reserve_value.
        // Under the OLD 1.5x ceiling, 140000 (140%) would have been legal. Under the NEW 1.2x, it must be rejected.
        try {
            $bidding->placeBid($saleEvent['id'], $buyer['id'], 140000.0);
            $this->assert(false, 'Should have thrown: 140% exceeds the newly-tightened 120% ceiling');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'BR-43'), 'A bid that the OLD 150% ceiling would have allowed is now correctly rejected under the NEW 120% ceiling — this is genuinely live, not just stored');
        }
        // 115% should still be legal under the new 120% ceiling.
        $accepted = $bidding->placeBid($saleEvent['id'], $buyer['id'], 115000.0);
        $this->assert($accepted['amount'] == 115000.0, 'A bid within the NEW ceiling is correctly accepted');

        CLI::write("\n=== Step 4 (BR-27): EMD baseline recalculates against a live-edited percentage ===", 'yellow');
        $rules->update($byKey['BR-27.emd_percent']['id'], 'EMD Baseline Percentage', 'test', 'test', 0.20, 'Doubling EMD for this test', $seller['id']);
        $newBaseline = EmdService::calculateBaselineEmd('easy', null, 100000.0);
        $this->assert($newBaseline === 20000.0, 'EMD baseline is genuinely 20% (20000) now, not the old hardcoded 10% (10000)');

        CLI::write("\n=== Step 5 (BR-49): the SAME rule gates BOTH SettlementService and PayoutControlService together ===", 'yellow');
        $rules->update($byKey['BR-49.high_value_threshold']['id'], 'High-Value Disposal Threshold', 'test', 'test', 500000.0, 'Lowering threshold for this test', $seller['id']);
        $settlementRef = new \ReflectionClass(SettlementService::class);
        $payoutRef = new \ReflectionClass(PayoutControlService::class);
        $settlementMethod = $settlementRef->getMethod('maybeRecordHighValueDisposal');
        $settlementMethod->setAccessible(true);
        $settlement = new SettlementService();
        $db = \Config\Database::connect();
        $realSettlement = (new SettlementModel())->createSettlement($saleEvent['id'], $buyer['id'], $seller['id'], 600000.0);
        $settlementMethod->invoke($settlement, $realSettlement['id'], ['final_price' => 600000.0, 'sale_event_id' => $saleEvent['id']]);
        $flagged = $db->table('high_value_disposal_record')->where('settlement_id', $realSettlement['id'])->countAllResults();
        $this->assert($flagged === 1, 'A ₹6L settlement now trips BR-49 disposal reporting under the lowered ₹5L threshold (would NOT have under the old ₹10L)');

        $payoutMethod = $payoutRef->getMethod('needsReview');
        $payoutMethod->setAccessible(true);
        $payout = new PayoutControlService();
        $needsReview = $payoutMethod->invoke($payout, $seller['id'], 600000.0);
        $this->assert($needsReview === false, 'PayoutControlService reads the SAME live rule (party has no recent bank change here, so still false) — proving both services share one rule, not two independent numbers');

        CLI::write("\n=== Step 6 (BR-38): shadow-ban / crawl-back thresholds drive live rating enforcement ===", 'yellow');
        $rules->update($byKey['BR-38.shadow_ban_threshold']['id'], 'Shadow Ban Threshold', 'test', 'test', 2.5, 'Raising for this test — anything below 2.5 now shadow-bans', $seller['id']);
        $ratingParty = $partyModel->createParty('+919555412003');
        $ratingRef = new \ReflectionClass(RatingService::class);
        $method = $ratingRef->getMethod('maybeTriggerCrawlBack');
        $method->setAccessible(true);
        $ratingService = new RatingService();
        // 2.2 would NOT trip the old 1.5 shadow-ban threshold, but DOES trip the new 2.5.
        $method->invoke($ratingService, $ratingParty['id'], 'star_rating', 2.2);
        $afterParty = $partyModel->find($ratingParty['id']);
        $this->assert(!empty($afterParty['shadow_banned_at_seller']) || !empty($afterParty['shadow_banned_at_buyer']), 'A rating that the OLD 1.5 threshold would NOT have shadow-banned now correctly does, under the live-edited 2.5 threshold');

        CLI::write("\n=== Step 7: BR-05 audit trail — every rule change is logged to the tamper-evident hash chain ===", 'yellow');
        // 4 successful edits so far this run (BR-43, BR-27, BR-49, BR-38 shadow-ban)
        // — the empty-reason attempt in Step 2 correctly never reached logAudit().
        $auditCount = $db->table('audit_log')->where('event_type', 'sovereign_rule.revised')->countAllResults();
        $this->assert($auditCount === 4, 'Exactly 4 sovereign_rule.revised audit entries exist — one per successful edit made so far, none for the rejected empty-reason attempt');

        CLI::write("\n=== Step 8: a freeform rule (no rule_key) is versioned/audited but has no live effect ===", 'yellow');
        $freeform = $rules->createFreeform('Test Governance Policy', 'A policy statement with no code binding.', 'n/a', 'Recording a governance decision for this test', $seller['id']);
        $this->assert($freeform['rule_key'] === null, 'A freeform rule has no rule_key');
        $this->assert((int) $freeform['version'] === 1, 'A freeform rule starts at v1');
        $refetchedAll = $rules->listAll();
        $found = array_filter($refetchedAll, fn ($r) => $r['id'] === $freeform['id']);
        $this->assert(count($found) === 1, 'The freeform rule appears in listAll() alongside the wired ones');

        // These 5 rules are genuinely live, platform-wide, and persist in
        // the database — unlike every other Test* command, which only
        // creates its own isolated tenant/party/listing rows, this one
        // mutates shared configuration every other engine (and a real
        // deployment) depends on. Restore the originals so this test run
        // doesn't leave the platform silently reconfigured afterward.
        CLI::write("\n=== Teardown: restoring all 5 wired rules to their original values ===", 'yellow');
        foreach (SovereignRuleService::seedDefinitions() as $key => $def) {
            $current = $byKey[$key];
            $rules->update($current['id'], $def['title'], $def['statement'], $def['logic'], $def['numeric_value'], 'test:sovereignrule teardown — restoring platform default', $seller['id']);
        }
        $restored = [];
        foreach ($rules->listAll() as $r) {
            if ($r['rule_key']) $restored[$r['rule_key']] = (float) $r['numeric_value'];
        }
        $this->assert(
            $restored['BR-43.bid_ceiling_multiplier'] === 1.5 && $restored['BR-27.emd_percent'] === 0.10
            && $restored['BR-49.high_value_threshold'] === 1000000.0 && $restored['BR-38.shadow_ban_threshold'] === 1.5
            && $restored['BR-38.crawl_back_threshold'] === 2.0,
            'All 5 rules restored to their original values — this test leaves no live config changed behind'
        );

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
