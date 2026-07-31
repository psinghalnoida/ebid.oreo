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
use App\Libraries\OfferService;
use App\Libraries\SettlementService;
use App\Libraries\ChronicleService;

class TestChronicle extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:chronicle';
    protected $description = 'Proves Section 7.10 (ADWITIX_Master.docx) — the Trading Session Chronicle is generated on real settlement completion, correctly masked (BR-16), and tamper-evident.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $settlementModel = new SettlementModel();
        $offers = new OfferService();
        $settlement = new SettlementService();
        $chronicles = new ChronicleService();

        CLI::write('=== Setup: a completed Buy-Now Trading Session ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Chronicle Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'chronicletest']);
        $seller = $partyModel->createParty('+919888901001');
        $buyer1 = $partyModel->createParty('+919888901002');
        $buyer2 = $partyModel->createParty('+919888901003');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Chronicle Test Yard', 'yard_location_pin' => '600010',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-CHRONICLE-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'reserve_value' => 80000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer1['id'], 'van', 10000);
        $emdHoldModel->createHold($saleEvent['id'], $buyer2['id'], 'van', 10000);
        $offers->submitOffer($saleEvent['id'], $buyer1['id'], 90000);
        $offer2 = $offers->submitOffer($saleEvent['id'], $buyer2['id'], 96000);
        $offers->acceptOffer($saleEvent['id'], $offer2['id'], null);

        $s = $settlementModel->findBySaleEvent($saleEvent['id']);
        $settlement->confirmSellerNoc($s['id'], $seller['id']);
        $settlement->confirmBuyerNoc($s['id'], $buyer2['id']);
        $settlement->submitRating($s['id'], $buyer2['id'], 'buyer', 'good');
        $settlement->submitRating($s['id'], $seller['id'], 'seller', 'good');

        $completed = $settlementModel->find($s['id']);
        $this->assert($completed['status'] === 'completed', 'Settlement genuinely reached completed status');

        CLI::write("\n=== A Chronicle was generated automatically, not on request ===", 'yellow');
        $chronicle = $chronicles->findForSaleEvent($saleEvent['id']);
        $this->assert($chronicle !== null, 'A Chronicle exists for this Trading Session with no explicit generate() call from the test');
        $this->assert(str_starts_with($chronicle['reference_number'], 'CHR-' . date('Ymd')), 'Reference number follows the CHR-YYYYMMDD-xxxxxxxx convention');
        $this->assert(strlen($chronicle['verification_token']) === 48, 'Verification token is a real 48-hex-char random value, not a short/guessable one');
        $this->assert((int) $chronicle['version'] === 1, 'First Chronicle for this Trading Session is version 1');

        CLI::write("\n=== Section 7.10 content: what was listed, the result, improvement, transaction ===", 'yellow');
        $reportData = json_decode($chronicle['report_data'], true);
        $this->assert($reportData['listing']['category'] === 'Machinery', 'Listing category captured in the snapshot');
        $this->assert((float) $reportData['result']['finalPrice'] === 96000.0, 'Final price matches the accepted offer');
        $this->assert((float) $reportData['improvement']['reserveValue'] === 80000.0, 'Reserve Value captured for the improvement calculation');
        $this->assert(abs($reportData['improvement']['improvementPercent'] - 20.0) < 0.01, 'Improvement correctly computed as (96000-80000)/80000 = 20.00%');
        $this->assert($reportData['transaction']['tdsRatePercent'] == 10.00, 'BR-53\'s confirmed 10% TDS rate reflected in the transaction summary');
        $this->assert((float) $reportData['transaction']['tdsAmount'] > 0, 'A real, non-zero TDS amount computed, not a placeholder');

        CLI::write("\n=== BR-16: participant identity stays masked in the Chronicle ===", 'yellow');
        $this->assert((int) $reportData['participation']['distinctParticipants'] === 2, 'Both distinct offerors counted (2), matching the real fixture');
        $reportJson = json_encode($reportData);
        $this->assert(!str_contains($reportJson, $buyer1['id']), 'The losing offeror\'s Party ID never appears anywhere in the report content');
        $this->assert(!str_contains($reportJson, $buyer2['id']), 'The winning buyer\'s Party ID never appears anywhere in the report content either');
        $this->assert(!str_contains($reportJson, $seller['id']), 'The seller\'s own Party ID never appears in the report content');
        $this->assert(count($reportData['participation']['offerProgression']) === 2, 'Both offers recorded in the progression, amount + status only');

        CLI::write("\n=== Tamper-evidence: content_hash genuinely matches the stored report_data ===", 'yellow');
        $recomputed = hash('sha256', $chronicle['report_data']);
        $this->assert($recomputed === $chronicle['content_hash'], 'A fresh hash of the stored report_data matches the certified content_hash');
        $this->assert(!empty($reportData['auditChainRecordHash']), 'The Chronicle carries a real pointer into the BR-05 hash-chained audit trail');

        CLI::write("\n=== Retrieval: by token (QR path) and by ID with real authorization ===", 'yellow');
        $byToken = $chronicles->getByToken($chronicle['verification_token']);
        $this->assert($byToken !== null && $byToken['id'] === $chronicle['id'], 'getByToken() retrieves the same Chronicle the QR would encode');
        $this->assert($chronicles->getByToken('not-a-real-token') === null, 'An invalid token retrieves nothing');

        $sellerAuthorized = $chronicles->findIfAuthorized($chronicle['id'], $seller['id']);
        $this->assert($sellerAuthorized !== null, 'The Market Maker (Seller) on this Trading Session is authorized to fetch their own Chronicle by ID');
        $strangerAuthorized = $chronicles->findIfAuthorized($chronicle['id'], $buyer1['id']);
        $this->assert($strangerAuthorized === null, 'A party who was not the Seller on this Trading Session is correctly denied direct-ID access');

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
