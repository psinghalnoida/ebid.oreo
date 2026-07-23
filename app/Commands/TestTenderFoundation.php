<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Libraries\TenderService;

class TestTenderFoundation extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:tenderfoundation';
    protected $description = 'Runs the Tender Auction foundation against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $tender = new TenderService();

        CLI::write('=== BR-12: Tender is Company Shop exclusive ===', 'yellow');
        $generalTenant = $tenantModel->createTenant(['name' => 'General Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'tendergeneraltest']);
        $companyShop = $tenantModel->createTenant(['name' => 'The Company Shop', 'tenant_class' => 'company_shop', 'subdomain' => 'companyshoptest']);

        $rejected = false;
        try {
            $tender->validateCompanyShopOnly($generalTenant['id']);
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'BR-12');
        }
        $this->assert($rejected, 'A general tenant is correctly blocked from creating a Tender');

        $allowed = true;
        try {
            $tender->validateCompanyShopOnly($companyShop['id']);
        } catch (\RuntimeException $e) {
            $allowed = false;
        }
        $this->assert($allowed, 'A Company Shop tenant is correctly allowed');

        CLI::write("\n=== Setup: a real Tender sale event ===", 'yellow');
        $seller = $partyModel->createParty('+919888401001');
        $buyer1 = $partyModel->createParty('+919888401002');
        $buyer2 = $partyModel->createParty('+919888401003');
        $stranger = $partyModel->createParty('+919888401004');

        $listing = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Antiques', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600025',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TENDER-001',
            'sale_format' => 'tender', 'status' => 'active',
        ]);

        CLI::write("\n=== Interest registration ===", 'yellow');
        $tender->registerInterest($saleEvent['id'], $buyer1['id']);
        $dup = false;
        try {
            $tender->registerInterest($saleEvent['id'], $buyer1['id']);
        } catch (\RuntimeException $e) {
            $dup = str_contains($e->getMessage(), 'already registered');
        }
        $this->assert($dup, 'Duplicate interest registration correctly blocked');

        CLI::write("\n=== Eligibility: from interest, and added directly ===", 'yellow');
        $elig1 = $tender->grantEligibility($saleEvent['id'], $buyer1['id'], $seller['id']);
        $this->assert($elig1['source'] === 'interest', 'Buyer1 correctly tagged source=interest');

        $elig2 = $tender->grantEligibility($saleEvent['id'], $buyer2['id'], $seller['id']);
        $this->assert($elig2['source'] === 'direct', 'Buyer2 correctly tagged source=direct');

        $wrongSeller = false;
        try {
            $tender->grantEligibility($saleEvent['id'], $stranger['id'], $buyer1['id']);
        } catch (\RuntimeException $e) {
            $wrongSeller = str_contains($e->getMessage(), 'Only the listing\'s seller');
        }
        $this->assert($wrongSeller, 'A non-seller cannot grant eligibility');

        $this->assert($tender->isEligible($saleEvent['id'], $buyer1['id']), 'Buyer1 is eligible');
        $this->assert($tender->isEligible($saleEvent['id'], $buyer2['id']), 'Buyer2 is eligible');
        $this->assert(!$tender->isEligible($saleEvent['id'], $stranger['id']), 'The stranger is correctly NOT eligible');

        CLI::write("\n=== Documents: Terms of Sale, required documents ===", 'yellow');
        $tender->publishDocument($saleEvent['id'], $seller['id'], 'terms_of_sale', 'Terms of Sale', null, 'Buyer must arrange own transport.');
        $tender->publishDocument($saleEvent['id'], $seller['id'], 'emd_information', 'EMD Information', null, 'EMD via bank transfer.');
        $docs = $tender->getDocuments($saleEvent['id']);
        $this->assert(count($docs) === 2, 'Both documents published and retrievable');

        $wrongUploader = false;
        try {
            $tender->publishDocument($saleEvent['id'], $buyer1['id'], 'required_document', 'Fake doc', null, 'test');
        } catch (\RuntimeException $e) {
            $wrongUploader = str_contains($e->getMessage(), 'Only the listing\'s seller');
        }
        $this->assert($wrongUploader, 'A non-seller cannot publish Tender documents');

        CLI::write("\n=== Stakeholder read-only access, no account needed ===", 'yellow');
        $tokenRow = $tender->generateStakeholderLink($saleEvent['id'], $seller['id'], 'Insurer XYZ');
        $this->assert(strlen($tokenRow['token']) === 48, 'A real, sufficiently long random token generated');

        $resolved = $tender->resolveStakeholderToken($tokenRow['token']);
        $this->assert($resolved['id'] === $saleEvent['id'], 'The token correctly resolves to the right sale event');

        $badToken = false;
        try {
            $tender->resolveStakeholderToken('not-a-real-token');
        } catch (\RuntimeException $e) {
            $badToken = true;
        }
        $this->assert($badToken, 'An invalid token is correctly rejected');

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
