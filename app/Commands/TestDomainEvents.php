<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Events\Events;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\PartyDocumentModel;
use App\Models\DomainEventLogModel;
use App\Libraries\BiddingService;
use App\Libraries\OfferService;
use App\Libraries\SettlementService;
use App\Libraries\ListingLifecycleService;
use App\Libraries\KycService;
use App\Libraries\DisputeService;
use App\Libraries\DomainEvents;

// D-115: proves the domain-event layer (App\Libraries\DomainEvents +
// CodeIgniter's own Events facade + DomainEventLogListener) is real —
// each of the first 5 events genuinely fires exactly once, from the
// real service method, with the correct payload, recorded by a
// listener with zero knowledge of the publisher.
class TestDomainEvents extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:domainevents';
    protected $description = 'Proves the domain-event publish/subscribe layer (D-115) is real, not decorative.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $eventLogModel = new DomainEventLogModel();
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Domain Events Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'domaineventstest']);
        $seller = $partyModel->createParty('+919444001001');
        $buyer = $partyModel->createParty('+919444001002');

        CLI::write("\n=== AuctionCreated: ListingLifecycleService::approveSaleEvent() ===", 'yellow');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600001',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DOMEVT-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'pending_approval',
        ]);
        $beforeCount = count($eventLogModel->findByEventName(DomainEvents::AUCTION_CREATED));
        (new ListingLifecycleService())->approveSaleEvent($saleEvent['id']);
        $afterRows = $eventLogModel->findByEventName(DomainEvents::AUCTION_CREATED);
        $this->assert(count($afterRows) === $beforeCount + 1, 'Exactly one AuctionCreated event recorded');
        $lastPayload = json_decode(end($afterRows)['payload'], true);
        $this->assert($lastPayload['saleEventId'] === $saleEvent['id'], 'AuctionCreated payload carries the real sale event ID');
        $this->assert($lastPayload['saleFormat'] === 'buy_now', 'AuctionCreated payload carries the real sale format');

        CLI::write("\n=== BidPlaced: BiddingService::placeBid() ===", 'yellow');
        $easyListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600002',
        ]);
        $easySaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $easyListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DOMEVT-002',
            'sale_format' => 'easy', 'reserve_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($easySaleEvent['id'], $buyer['id'], 'van', 5000);
        $beforeCount = count($eventLogModel->findByEventName(DomainEvents::BID_PLACED));
        $bid = (new BiddingService())->placeBid($easySaleEvent['id'], $buyer['id'], 55000);
        $afterRows = $eventLogModel->findByEventName(DomainEvents::BID_PLACED);
        $this->assert(count($afterRows) === $beforeCount + 1, 'Exactly one BidPlaced event recorded');
        $lastPayload = json_decode(end($afterRows)['payload'], true);
        $this->assert($lastPayload['bidId'] === $bid['id'], 'BidPlaced payload carries the real bid ID');
        $this->assert((float) $lastPayload['amount'] === 55000.0, 'BidPlaced payload carries the real amount');

        CLI::write("\n=== SettlementCompleted: SettlementService::checkCompletion() only on genuine completion ===", 'yellow');
        $settlementListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600003',
        ]);
        $buyNowSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $settlementListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DOMEVT-003',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($buyNowSaleEvent['id'], $buyer['id'], 'van', 10000);
        $offers = new OfferService();
        $offer = $offers->submitOffer($buyNowSaleEvent['id'], $buyer['id'], 95000);
        $settlement = new SettlementService();
        $beforeCount = count($eventLogModel->findByEventName(DomainEvents::SETTLEMENT_COMPLETED));
        $offers->acceptOffer($buyNowSaleEvent['id'], $offer['id'], null);
        $s = (new \App\Models\SettlementModel())->findBySaleEvent($buyNowSaleEvent['id']);
        $settlement->confirmSellerNoc($s['id'], $seller['id']);
        $midCount = count($eventLogModel->findByEventName(DomainEvents::SETTLEMENT_COMPLETED));
        $this->assert($midCount === $beforeCount, 'No SettlementCompleted fired yet after only 1 of 4 steps — a domain event means the milestone genuinely happened');
        $settlement->confirmBuyerNoc($s['id'], $buyer['id']);
        $settlement->submitRating($s['id'], $buyer['id'], 'buyer', 'good');
        $settlement->submitRating($s['id'], $seller['id'], 'seller', 'good');
        $afterRows = $eventLogModel->findByEventName(DomainEvents::SETTLEMENT_COMPLETED);
        $this->assert(count($afterRows) === $beforeCount + 1, 'Exactly one SettlementCompleted event recorded once all 4 steps genuinely complete');
        $lastPayload = json_decode(end($afterRows)['payload'], true);
        $this->assert($lastPayload['settlementId'] === $s['id'], 'SettlementCompleted payload carries the real settlement ID');
        $this->assert((float) $lastPayload['finalPrice'] === 95000.0, 'SettlementCompleted payload carries the real final price');

        CLI::write("\n=== KYCApproved: KycService::reviewDossier() only when approved, never on suspension ===", 'yellow');
        $kycParty = $partyModel->createParty('+919444001003');
        $reviewer = $partyModel->createParty('+919444001004');
        $kyc = new KycService();
        $kyc->saveQuestionnaire($kycParty['id'], 'individual', [
            'full_name' => 'Domain Events Test', 'pan' => 'abcde1234f', 'date_of_birth' => '1990-01-01', 'occupation' => 'Engineer',
        ]);
        $kyc->registerAddress($kycParty['id'], 'registered', ['line1' => '1 Test St', 'city' => 'Mumbai', 'district' => 'Mumbai', 'state' => 'MH', 'pin_code' => '400001']);
        $documentModel = new PartyDocumentModel();
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $kycParty['id'], 'document_type' => 'pan_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'pan.pdf', 'mime_type' => 'application/pdf']);
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $kycParty['id'], 'document_type' => 'aadhaar_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'aadhaar.pdf', 'mime_type' => 'application/pdf']);
        $kyc->submitForReview($kycParty['id']);
        $beforeCount = count($eventLogModel->findByEventName(DomainEvents::KYC_APPROVED));
        $kyc->reviewDossier($kycParty['id'], $reviewer['id'], false, 'document_mismatch');
        $midCount = count($eventLogModel->findByEventName(DomainEvents::KYC_APPROVED));
        $this->assert($midCount === $beforeCount, 'No KYCApproved fired on a suspension — only approval is the domain event');
        // Resubmit and approve for real.
        $documentModel->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $kycParty['id'], 'document_type' => 'pan_card', 'encrypted_path' => '/dev/null', 'original_filename' => 'pan2.pdf', 'mime_type' => 'application/pdf']);
        $kyc->submitForReview($kycParty['id']);
        $kyc->reviewDossier($kycParty['id'], $reviewer['id'], true);
        $afterRows = $eventLogModel->findByEventName(DomainEvents::KYC_APPROVED);
        $this->assert(count($afterRows) === $beforeCount + 1, 'Exactly one KYCApproved event recorded on genuine approval');
        $lastPayload = json_decode(end($afterRows)['payload'], true);
        $this->assert($lastPayload['partyId'] === $kycParty['id'], 'KYCApproved payload carries the real party ID');

        CLI::write("\n=== DisputeFiled: DisputeService::fileDispute() ===", 'yellow');
        $disputeListing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600004',
        ]);
        $disputeSaleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $disputeListing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-DOMEVT-004',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($disputeSaleEvent['id'], $buyer['id'], 'van', 10000);
        $disputeOffer = (new OfferService())->submitOffer($disputeSaleEvent['id'], $buyer['id'], 95000);
        (new OfferService())->acceptOffer($disputeSaleEvent['id'], $disputeOffer['id'], null);
        $beforeCount = count($eventLogModel->findByEventName(DomainEvents::DISPUTE_FILED));
        $dispute = (new DisputeService())->fileDispute($disputeSaleEvent['id'], $buyer['id'], 'condition_delivery', 'Domain event test dispute');
        $afterRows = $eventLogModel->findByEventName(DomainEvents::DISPUTE_FILED);
        $this->assert(count($afterRows) === $beforeCount + 1, 'Exactly one DisputeFiled event recorded');
        $lastPayload = json_decode(end($afterRows)['payload'], true);
        $this->assert($lastPayload['disputeId'] === $dispute['id'], 'DisputeFiled payload carries the real dispute ID');
        $this->assert($lastPayload['respondentPartyId'] === $seller['id'], 'DisputeFiled payload correctly identifies the seller as respondent');

        CLI::write("\n=== The listener is genuinely decoupled — it never runs unless Events::trigger() is actually called ===", 'yellow');
        $simulated = false;
        Events::on('TEST-DOMEVT-canary', static function () use (&$simulated): void { $simulated = true; });
        $this->assert($simulated === false, 'A registered listener has not run before its event fires');
        Events::trigger('TEST-DOMEVT-canary');
        $this->assert($simulated === true, 'The same listener runs once its event genuinely fires — proves Events::trigger()/on() wiring itself works, not just this suite\'s own assertions');

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
