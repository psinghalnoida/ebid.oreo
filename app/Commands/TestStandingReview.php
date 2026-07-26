<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SellerApplicationModel;
use App\Libraries\StandingReviewService;
use App\Libraries\SellerApplicationService;

class TestStandingReview extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:standingreview';
    protected $description = 'Proves BR-61 Standing Review is real, not just tracked.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $sellerAppModel = new SellerApplicationModel();
        $standingReview = new StandingReviewService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Standing Review Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'standingreviewtest']);
        $seller = $partyModel->createParty('+919111701001');
        $tenantAdmin = $partyModel->createParty('+919111701002');

        $appId = \App\Libraries\Uuid::v4();
        $sellerAppModel->insert([
            'id' => $appId, 'party_id' => $seller['id'], 'tenant_id' => $tenant['id'],
            'status' => 'approved', 'decided_by_party_id' => $tenantAdmin['id'],
            'applied_at' => date('Y-m-d H:i:s'), 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        (new \App\Models\PartyRoleModel())->grantRole($seller['id'], 'seller', $tenant['id']);
        (new \App\Models\PartyRoleModel())->grantRole($tenantAdmin['id'], 'tenant_admin', $tenant['id']);

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600060',
        ]);

        CLI::write("\n=== The general complaint counter genuinely triggers a case at 10 ===", 'yellow');
        for ($i = 1; $i <= 10; $i++) {
            $standingReview->recordComplaint($seller['id'], "Test complaint #{$i}");
        }
        $this->assert(!$standingReview->hasOpenCase($seller['id']), 'At exactly 10, no case has opened yet — the trigger is "exceeding" 10');
        $standingReview->recordComplaint($seller['id'], 'Test complaint #11');
        $this->assert($standingReview->hasOpenCase($seller['id']), 'The 11th complaint genuinely opens a real Standing Review case');

        $db = \Config\Database::connect();
        $case = $db->table('dispute')->where('respondent_party_id', $seller['id'])->where('category', 'standing_review')->get()->getRowArray();
        $this->assert($case !== null, 'The case is a genuine row in the shared dispute table, not a separate parallel system');
        $this->assert($case['sale_event_id'] === null, 'Correctly has no sale_event_id — system-initiated, not tied to one transaction');
        $this->assert($case['filed_by_party_id'] === null, 'Correctly has no filer — system-initiated, the seller is the subject not a respondent to an accuser');

        CLI::write("\n=== A second complaint does NOT open a duplicate case while one is open ===", 'yellow');
        $standingReview->recordComplaint($seller['id'], 'Test complaint #12');
        $openCases = $db->table('dispute')->where('respondent_party_id', $seller['id'])->where('category', 'standing_review')->countAllResults();
        $this->assert($openCases === 1, 'Still exactly one open case, not a duplicate');

        CLI::write("\n=== A real ruling reuses SellerApplicationService::suspendSeller, per the confirmed decision ===", 'yellow');
        $standingReview->ruleOnCase($case['id'], $tenantAdmin['id'], $tenant['id'], 'suspension', 'Confirmed pattern of auction rejections.');
        $roleAfterSuspend = $db->table('party_role')
            ->where('party_id', $seller['id'])->where('tenant_id', $tenant['id'])->where('role', 'seller')
            ->get()->getRowArray();
        $this->assert($roleAfterSuspend['revoked_at'] !== null, 'The seller\'s actual selling role on this tenant is genuinely revoked — the real, existing D-31 mechanism, not a new parallel one');

        $ruledCase = $db->table('dispute')->where('id', $case['id'])->get()->getRowArray();
        $this->assert($ruledCase['ruling_outcome'] === 'suspension', 'The correct, dedicated enum value is recorded — not a semantic mismatch reusing an unrelated outcome');

        $resetParty = $partyModel->find($seller['id']);
        $this->assert((int) $resetParty['standing_review_complaint_count'] === 0, 'The complaint counter genuinely reset to zero on conclusion');

        CLI::write("\n=== CBS violations are a genuinely separate, lifetime-counted ladder ===", 'yellow');
        $cbsSeller = $partyModel->createParty('+919111701003');
        $cbsListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $cbsSeller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600061',
        ]);

        $r1 = $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $this->assert($r1['tier'] === 'warning', '1st CBS offense is correctly a warning only');
        $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $r3 = $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $this->assert($r3['tier'] === 'tenant_admin_discretion', '3rd CBS offense correctly escalates to Tenant Admin discretion');
        $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $r5 = $standingReview->recordCbsViolation($cbsSeller['id'], $tenantAdmin['id'], $cbsListing['id']);
        $this->assert($r5['tier'] === 'saas_admin_exclusive', '5th CBS offense correctly moves to SaaS Admin exclusive authority');

        CLI::write("\n=== The CBS ladder is genuinely lifetime — confirmed via a second Standing Review conclusion ===", 'yellow');
        for ($i = 1; $i <= 11; $i++) {
            $standingReview->recordComplaint($cbsSeller['id'], "Pushing to threshold #{$i}");
        }
        $cbsCase = $db->table('dispute')->where('respondent_party_id', $cbsSeller['id'])->where('category', 'standing_review')->get()->getRowArray();
        $sellerAppModel->insert([
            'id' => \App\Libraries\Uuid::v4(), 'party_id' => $cbsSeller['id'], 'tenant_id' => $tenant['id'],
            'status' => 'approved', 'decided_by_party_id' => $tenantAdmin['id'],
            'applied_at' => date('Y-m-d H:i:s'), 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        (new \App\Models\PartyRoleModel())->grantRole($cbsSeller['id'], 'seller', $tenant['id']);
        $standingReview->ruleOnCase($cbsCase['id'], $tenantAdmin['id'], $tenant['id'], 'no_action', 'Reviewed, no further action needed this cycle.');

        $cbsPartyAfter = $partyModel->find($cbsSeller['id']);
        $this->assert((int) $cbsPartyAfter['standing_review_cbs_offense_count'] === 5, 'The CBS offense count is genuinely untouched by the general counter\'s reset — still 5, confirming the lifetime-count decision holds');
        $this->assert((int) $cbsPartyAfter['standing_review_complaint_count'] === 0, 'But the general complaint counter did reset, as it should');

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
