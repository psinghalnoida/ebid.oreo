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
use App\Libraries\OfferService;
use App\Libraries\DisputeService;
use App\Libraries\BiddingService;

class TestDispute extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:dispute';
    protected $description = 'Runs the Dispute Resolution Framework (BR-40) against real data.';

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
        $offers = new OfferService();
        $dispute = new DisputeService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Dispute Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'disputetest', 'buyer_fee_percent' => 5.00]);
        $seller = $partyModel->createParty('+919777701001');
        $buyer = $partyModel->createParty('+919777701002');
        $tenantAdmin = $partyModel->createParty('+919777701003');
        $superAdmin = $partyModel->createParty('+919777701004');
        $roleModel->promoteTenantAdmin($tenantAdmin['id'], $tenant['id']);
        $roleModel->grantRole($superAdmin['id'], 'super_admin', null);

        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600009',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISPUTE-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 10000);
        $offer = $offers->submitOffer($saleEvent['id'], $buyer['id'], 95000);
        $offers->acceptOffer($saleEvent['id'], $offer['id'], null);

        CLI::write("\n=== BR-40: Tender exclusion ===", 'yellow');
        $tenderListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Antiques', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600011',
        ]);
        $tenderEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $tenderListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISPUTE-TENDER-EXCL-001',
            'sale_format' => 'tender', 'status' => 'active',
        ]);
        $rejected = false;
        try {
            $dispute->fileDispute($tenderEvent['id'], $buyer['id'], 'payment', 'test');
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'Tender');
        }
        $this->assert($rejected, 'BR-40: Tender Auctions correctly excluded from dispute filing');

        CLI::write("\n=== File a Condition/Delivery dispute (buyer against seller) ===", 'yellow');
        $d1 = $dispute->fileDispute($saleEvent['id'], $buyer['id'], 'condition_delivery', 'Item does not match the listing description');
        $this->assert($d1['status'] === 'evidence_window', 'Dispute opens directly into evidence_window');
        $this->assert($d1['respondent_party_id'] === $seller['id'], 'Respondent correctly identified as the seller');
        $this->assert($d1['ruling_authority_type'] === 'tenant_admin', 'condition_delivery routes to Tenant Admin, not Super Admin');

        CLI::write("\n=== Only the two real parties can submit evidence ===", 'yellow');
        $randomParty = $partyModel->createParty('+919777701005');
        $blockedEvidence = false;
        try {
            $dispute->submitEvidence($d1['id'], $randomParty['id'], 'irrelevant');
        } catch (\RuntimeException $e) {
            $blockedEvidence = true;
        }
        $this->assert($blockedEvidence, 'A party with no stake in the dispute cannot submit evidence');

        $dispute->submitEvidence($d1['id'], $buyer['id'], 'Photos show item is a different colour than listed');
        $dispute->submitEvidence($d1['id'], $seller['id'], 'Photos were accurate at time of listing');
        $evidence = $dispute->getEvidence($d1['id']);
        $this->assert(count($evidence) === 2, 'Both parties\' evidence recorded');

        CLI::write("\n=== A non-Tenant-Admin cannot rule ===", 'yellow');
        $blockedRuling = false;
        try {
            $dispute->ruleOnDispute($d1['id'], $buyer['id'], 'dismissed', 'test');
        } catch (\RuntimeException $e) {
            $blockedRuling = str_contains($e->getMessage(), 'BR-40');
        }
        $this->assert($blockedRuling, 'A random party (not Tenant Admin) cannot rule on the dispute');

        CLI::write("\n=== Tenant Admin rules: rating_consequence against the seller ===", 'yellow');
        $sellerBefore = $partyModel->find($seller['id']);
        $ruled = $dispute->ruleOnDispute($d1['id'], $tenantAdmin['id'], 'rating_consequence', 'Photos confirmed item colour discrepancy — seller at fault', $seller['id']);
        $this->assert($ruled['status'] === 'ruled', 'Dispute status = ruled');
        $this->assert($ruled['ruling_rationale'] !== null, 'Rationale recorded');

        $sellerAfter = $partyModel->find($seller['id']);
        $this->assert(
            (float) $sellerAfter['seller_star_rating'] < (float) $sellerBefore['seller_star_rating'],
            "Seller's rating actually decreased from the ruling ({$sellerBefore['seller_star_rating']} -> {$sellerAfter['seller_star_rating']}), not just recorded as an outcome label"
        );

        CLI::write("\n=== Appeal: Tenant Admin ruling can be appealed once ===", 'yellow');
        $appealed = $dispute->fileAppeal($d1['id'], $seller['id']);
        $this->assert($appealed['status'] === 'appealed', 'Dispute status = appealed');

        $nonSuperAdminAppealBlocked = false;
        try {
            $dispute->ruleOnAppeal($d1['id'], $tenantAdmin['id'], 'test');
        } catch (\RuntimeException $e) {
            $nonSuperAdminAppealBlocked = true;
        }
        $this->assert($nonSuperAdminAppealBlocked, 'A Tenant Admin (not Super Admin) cannot rule on an appeal');

        $appealRuling = $dispute->ruleOnAppeal($d1['id'], $superAdmin['id'], 'Original ruling upheld — evidence was conclusive');
        $this->assert($appealRuling['status'] === 'closed', 'Appeal ruling closes the dispute');
        $this->assert($appealRuling['appeal_rationale'] !== null, 'Appeal rationale recorded');

        CLI::write("\n=== A direct Super Admin ruling cannot be appealed ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600012',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISPUTE-002',
            'sale_format' => 'buy_now', 'expected_value' => 40000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $buyer['id'], 'van', 4000);
        $offer2 = $offers->submitOffer($saleEvent2['id'], $buyer['id'], 38000);
        $offers->acceptOffer($saleEvent2['id'], $offer2['id'], null);

        $d2 = $dispute->fileDispute($saleEvent2['id'], $seller['id'], 'buyer_non_response', 'Buyer has gone silent on confirming settlement');
        $this->assert($d2['ruling_authority_type'] === 'super_admin', 'buyer_non_response correctly routes to Super Admin');

        $dispute->ruleOnDispute($d2['id'], $superAdmin['id'], 'dismissed', 'Buyer confirmed via alternate channel — claim not substantiated');
        $notAppealable = false;
        try {
            $dispute->fileAppeal($d2['id'], $seller['id']);
        } catch (\RuntimeException $e) {
            $notAppealable = str_contains($e->getMessage(), 'final');
        }
        $this->assert($notAppealable, 'A direct Super Admin ruling correctly cannot be appealed');

        CLI::write("\n=== order_forfeiture actually forfeits EMD, not just records the outcome ===", 'yellow');
        $listing3 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Vehicles', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600013',
        ]);
        $saleEvent3 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing3['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DISPUTE-003',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent3['id'], $buyer['id'], 'van', 5000);
        $bidding = new BiddingService();
        $bidding->placeBid($saleEvent3['id'], $buyer['id'], 55000);

        $d3 = $dispute->fileDispute($saleEvent3['id'], $seller['id'], 'non_lifting_collection', 'Buyer refusing to collect the item after winning');
        $dispute->ruleOnDispute($d3['id'], $tenantAdmin['id'], 'order_forfeiture', 'Buyer confirmed unresponsive to collection attempts', $buyer['id']);

        $forfeitedHold = $emdHoldModel->findBySaleEventAndParty($saleEvent3['id'], $buyer['id']);
        $this->assert($forfeitedHold['status'] === 'forfeited', 'Buyer\'s EMD actually marked forfeited, not just the dispute outcome recorded');
        $this->assert((float) $forfeitedHold['forfeited_to_tenant_amount'] + (float) $forfeitedHold['forfeited_to_saas_amount'] + (float) $forfeitedHold['forfeited_to_seller_amount'] === 5000.0, 'Forfeiture allocation sums to the full held amount');

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
