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
use App\Libraries\TenderService;
use App\Libraries\TenderBiddingService;
use App\Libraries\TenderReviewService;

class TestTenderReview extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:tenderreview';
    protected $description = 'Runs the Tender post-auction review workflow against real data.';

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
        $tender = new TenderService();
        $bidding = new TenderBiddingService();
        $review = new TenderReviewService();

        CLI::write('=== Setup: 3 eligible buyers, all bid ===', 'yellow');
        $companyShop = $tenantModel->createTenant(['name' => 'Review Test Company Shop', 'tenant_class' => 'company_shop', 'subdomain' => 'tenderreviewtest', 'buyer_fee_percent' => 5.00]);
        $seller = $partyModel->createParty('+919888601001');
        $tenantAdmin = $partyModel->createParty('+919888601002');
        $buyerA = $partyModel->createParty('+919888601003');
        $buyerB = $partyModel->createParty('+919888601004');
        $buyerC = $partyModel->createParty('+919888601005');

        (new \App\Models\PartyRoleModel())->promoteTenantAdmin($tenantAdmin['id'], $companyShop['id']);

        $listing = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Antiques', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600029',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TREV-001',
            'sale_format' => 'tender', 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        foreach ([$buyerA, $buyerB, $buyerC] as $b) {
            $tender->grantEligibility($saleEvent['id'], $b['id'], $seller['id']);
            $tender->logManualEmd($saleEvent['id'], $b['id'], 0, null, 'EMD waived for this test scenario', $seller['id']);
        }
        $bidding->placeBid($saleEvent['id'], $buyerC['id'], 50000);
        $bidding->placeBid($saleEvent['id'], $buyerB['id'], 60000);
        $bidding->placeBid($saleEvent['id'], $buyerA['id'], 70000);

        CLI::write("\n=== Only the seller can close bidding ===", 'yellow');
        $wrongCloser = false;
        try {
            $review->closeBiddingAndDeclareProvisional($saleEvent['id'], $buyerA['id']);
        } catch (\RuntimeException $e) {
            $wrongCloser = str_contains($e->getMessage(), 'seller');
        }
        $this->assert($wrongCloser, 'A non-seller cannot close bidding');

        $round1 = $review->closeBiddingAndDeclareProvisional($saleEvent['id'], $seller['id']);
        $this->assert($round1['party_id'] === $buyerA['id'], 'Round 1 provisional winner is correctly H1 (buyerA)');
        $this->assert((int) $round1['round_number'] === 1, 'Round number starts at 1');
        $this->assert($round1['status'] === 'provisional', 'Status starts provisional');

        CLI::write("\n=== Only Tenant Admin can act on the review ===", 'yellow');
        $wrongActor = false;
        try {
            $review->rejectAndCascade($round1['id'], $seller['id'], 'test');
        } catch (\RuntimeException $e) {
            $wrongActor = str_contains($e->getMessage(), 'Tenant Admin');
        }
        $this->assert($wrongActor, 'The seller cannot reject on behalf of stakeholders');

        CLI::write("\n=== Extension granted, logged with a reason ===", 'yellow');
        $extended = $review->grantExtension($round1['id'], $tenantAdmin['id'], 'Buyer A requested more time for EMD');
        $this->assert($extended['status'] === 'extension_granted', 'Status correctly moved to extension_granted');
        $this->assert($extended['extension_reason'] !== null, 'Extension reason logged');

        CLI::write("\n=== Rejection cascades to H2, releasing the rejected winner's EMD ===", 'yellow');
        $emdHoldModel->createHold($saleEvent['id'], $buyerA['id'], 'manual_offline', 7000);
        $round2 = $review->rejectAndCascade($extended['id'], $tenantAdmin['id'], 'Surveyor found undisclosed damage');

        $this->assert($round2['party_id'] === $buyerB['id'], 'Cascade correctly moved to H2 (buyerB)');
        $this->assert((int) $round2['round_number'] === 2, 'Round number incremented to 2');

        $releasedHold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyerA['id']);
        $this->assert($releasedHold['status'] === 'released', 'Rejected winner\'s EMD correctly released');

        CLI::write("\n=== A second rejection cascades to H3, skipping buyerA ===", 'yellow');
        $round3 = $review->rejectAndCascade($round2['id'], $tenantAdmin['id'], 'Insurer also rejected');
        $this->assert($round3['party_id'] === $buyerC['id'], 'Cascade correctly moved to H3 (buyerC), skipped buyerA entirely');
        $this->assert((int) $round3['round_number'] === 3, 'Round number incremented to 3');

        CLI::write("\n=== Confirming creates a real Settlement and closes the sale event ===", 'yellow');
        $confirmed = $review->confirmWinner($round3['id'], $tenantAdmin['id']);
        $this->assert($confirmed['status'] === 'confirmed', 'Review status = confirmed');

        $closedEvent = $saleEventModel->find($saleEvent['id']);
        $this->assert($closedEvent['status'] === 'closed_sold', 'Sale event correctly closed as closed_sold');
        $this->assert((float) $closedEvent['current_price'] === 50000.0, 'Final price reflects buyerC\'s bid, not the original H1\'s 70000');

        $settlement = $settlementModel->findBySaleEvent($saleEvent['id']);
        $this->assert($settlement !== null, 'A real Settlement record created — Tender funnels into the same dual-NOC gate');
        $this->assert($settlement['buyer_party_id'] === $buyerC['id'], 'Settlement correctly names buyerC, not the original H1');

        CLI::write("\n=== Auction report contains real data across every stage ===", 'yellow');
        $report = $review->generateAuctionReport($saleEvent['id']);
        $this->assert(count($report['eligible']) === 3, 'Report shows all 3 eligible participants');
        $this->assert(count($report['bidHistory']) === 3, 'Report shows the full 3-bid history');
        $this->assert(count($report['reviewRounds']) === 3, 'Report shows all 3 review rounds');

        CLI::write("\n=== Full cascade failure: no one left to cascade to ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $companyShop['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600030',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $companyShop['id'], 'ern' => 'TEST-TREV-002',
            'sale_format' => 'tender', 'status' => 'active',
            'scheduled_start_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'scheduled_end_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);
        $tender->grantEligibility($saleEvent2['id'], $buyerA['id'], $seller['id']);
        $tender->logManualEmd($saleEvent2['id'], $buyerA['id'], 0, null, 'EMD waived for this test scenario', $seller['id']);
        $bidding->placeBid($saleEvent2['id'], $buyerA['id'], 30000);
        $onlyReview = $review->closeBiddingAndDeclareProvisional($saleEvent2['id'], $seller['id']);
        $review->rejectAndCascade($onlyReview['id'], $tenantAdmin['id'], 'Only bidder rejected, nobody left');

        $failedEvent = $saleEventModel->find($saleEvent2['id']);
        $this->assert($failedEvent['status'] === 'cycle_ended_unsold', 'With nobody left, correctly resolves to cycle_ended_unsold');

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
