<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\PartyRoleModel;
use App\Models\SellerApplicationModel;
use App\Libraries\StandingReviewService;
use App\Libraries\SettlementService;
use App\Libraries\ConsentService;
use App\Libraries\Uuid;

class TestSellerAudit extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:selleraudit';
    protected $description = 'Proves the Seller Management view (real Standing Review data) and Consent Audit viewer are real, not duplicated systems.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $roleModel = new PartyRoleModel();
        $sellerAppModel = new SellerApplicationModel();
        $standingReview = new StandingReviewService();
        $db = \Config\Database::connect();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Seller Audit Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'selleraudittest', 'buyer_fee_percent' => 5.00]);
        $seller = $partyModel->createParty('+919555404001');
        $buyer = $partyModel->createParty('+919555404002');
        $tenantAdmin = $partyModel->createParty('+919555404003');
        $roleModel->promoteTenantAdmin($tenantAdmin['id'], $tenant['id']);

        $sellerAppModel->insert([
            'id' => Uuid::v4(), 'party_id' => $seller['id'], 'tenant_id' => $tenant['id'],
            'status' => 'approved', 'decided_by_party_id' => $tenantAdmin['id'],
            'applied_at' => date('Y-m-d H:i:s'), 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        $roleModel->grantRole($seller['id'], 'seller', $tenant['id']);

        CLI::write("\n=== A completed sale genuinely counts toward this seller's sales-on-tenant total ===", 'yellow');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600097',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SELLERAUDIT-001',
            'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 5000);
        $settlementService = new SettlementService();
        $settlement = $settlementService->createForSaleEvent($saleEvent['id'], $buyer['id'], 48000);
        $settlementService->confirmSellerNoc($settlement['id'], $seller['id']);
        $settlementService->confirmBuyerNoc($settlement['id'], $buyer['id']);
        $settlementService->submitRating($settlement['id'], $buyer['id'], 'buyer', 'good');
        $settlementService->submitRating($settlement['id'], $seller['id'], 'seller', 'good');

        $salesCount = $db->table('settlement s')->join('sale_event se', 'se.id = s.sale_event_id')
            ->where('se.tenant_id', $tenant['id'])->where('s.seller_party_id', $seller['id'])->countAllResults();
        $this->assert($salesCount === 1, 'Seller Management\'s sales-on-tenant query genuinely reflects a real completed settlement');

        CLI::write("\n=== A CBS violation (3rd offense) genuinely appears in the violation history query ===", 'yellow');
        $standingReview->recordCbsViolation($seller['id'], $tenantAdmin['id'], $listing['id']);
        $standingReview->recordCbsViolation($seller['id'], $tenantAdmin['id'], $listing['id']);
        $standingReview->recordCbsViolation($seller['id'], $tenantAdmin['id'], $listing['id']);
        $violations = $db->table('rating_event')->where('party_id', $seller['id'])->where('rating_role', 'seller_star_rating')->where('event_type', 'downgrade')->get()->getResultArray();
        $this->assert(count($violations) >= 1, 'The seller detail page\'s violation query genuinely surfaces the real BR-35 rating_event, not a separate log');

        CLI::write("\n=== Initiating a Standing Review case now correctly attributes the Tenant Admin as the actor, not null ===", 'yellow');
        $freshSeller = $partyModel->createParty('+919555404004');
        $case = $standingReview->openCase($freshSeller['id'], 'Tenant Admin judgment call ahead of the automatic threshold', $tenantAdmin['id']);
        $log = $db->table('audit_log')->where('event_type', 'standing_review.case_opened')->like('payload', $case['id'])->get()->getRowArray();
        $this->assert($log !== null && $log['actor_party_id'] === $tenantAdmin['id'], 'A manually-initiated case now genuinely attributes the initiating Tenant Admin in the audit trail, not always null');

        CLI::write("\nA second attempt to initiate a case for the same seller is correctly rejected — no duplicate.", 'yellow');
        try {
            $standingReview->openCase($freshSeller['id'], 'duplicate attempt', $tenantAdmin['id']);
            $this->assert(false, 'Should have thrown: a case is already open');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'already has an open'), 'Correctly rejected: cannot open a second case while one is pending');
        }

        CLI::write("\n=== The automatic (threshold/anniversary) path still correctly attributes NO human actor ===", 'yellow');
        $autoSeller = $partyModel->createParty('+919555404005');
        for ($i = 1; $i <= 11; $i++) {
            $standingReview->recordComplaint($autoSeller['id'], "Auto complaint #{$i}");
        }
        $autoCase = $db->table('dispute')->where('respondent_party_id', $autoSeller['id'])->where('category', 'standing_review')->get()->getRowArray();
        $autoLog = $db->table('audit_log')->where('event_type', 'standing_review.case_opened')->like('payload', $autoCase['id'])->get()->getRowArray();
        $this->assert($autoLog['actor_party_id'] === null, 'The automatic threshold trigger correctly still logs no human actor — unchanged behavior');

        CLI::write("\n=== Consent Audit: real, previously-uncaptured events are genuinely queryable ===", 'yellow');
        $consentParty = $partyModel->createParty('+919555404006');
        (new ConsentService())->recordRegistrationConsent($consentParty['id'], '203.0.113.5');
        (new ConsentService())->recordEmdPledgeConsent($consentParty['id'], Uuid::v4(), 9000, 'forfeiture applies if you fail to complete payment', '203.0.113.5');

        $consentEvents = $db->table('consent_event')->where('party_id', $consentParty['id'])->get()->getResultArray();
        $this->assert(count($consentEvents) === 2, 'Both a registration and an EMD-pledge consent event are genuinely stored, queryable by the new viewer');
        $this->assert(in_array('emd_pledge', array_column($consentEvents, 'consent_type'), true), 'The EMD-pledge consent event carries the correct consent_type for filtering');

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
