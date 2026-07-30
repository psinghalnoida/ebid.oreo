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
use App\Models\TenantFeeLedgerModel;
use App\Models\TenantMonthlyInvoiceModel;
use App\Libraries\EmdService;
use App\Libraries\OfferService;
use App\Libraries\SettlementService;
use App\Libraries\InvoiceService;
use App\Libraries\TenantBillingService;

// BR-08/31/32/33/34/56 (D-87/D-88): the new declining Success Fee
// schedule, the Fee Payer Election, and the monthly Tenant-billing
// mechanism that collects a Seller-Pays fee since the platform never
// touches the seller's 100% sale-value proceeds directly.
class TestSuccessFee extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:successfee';
    protected $description = 'Runs the BR-31/32/33/34 Success Fee schedule, Fee Payer Election, and D-88 monthly Tenant billing against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        CLI::write('=== BR-31: Success Fee bracket schedule — direct math ===', 'yellow');
        $this->assert(EmdService::calculateSuccessFee(500000) === 10000.0, '2% on 500000 (<=10L) = 10000');
        $this->assert(EmdService::calculateSuccessFee(1000000) === 20000.0, '2% on exactly 1000000 (10L boundary, inclusive) = 20000');
        $this->assert(EmdService::calculateSuccessFee(2000000) === 30000.0, '1.5% on 2000000 (10L-50L band) = 30000');
        $this->assert(EmdService::calculateSuccessFee(10000000) === 100000.0, '1% on 10000000 (50L-2Cr band) = 100000');
        $this->assert(EmdService::calculateSuccessFee(50000000) === 375000.0, '0.75% on 50000000 (2Cr-10Cr band) = 375000');
        $this->assert(EmdService::calculateSuccessFee(150000000) === 750000.0, '0.5% on 150000000 (>10Cr band) = 750000');
        $this->assert(EmdService::calculateSuccessFee(10000) === 500.0, 'BR-31: ₹500 minimum floor applied when 2% of 10000 (200) would be below it');

        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $settlementModel = new SettlementModel();
        $settlementService = new SettlementService();
        $invoices = new InvoiceService();
        $ledgerModel = new TenantFeeLedgerModel();
        $invoiceModel = new TenantMonthlyInvoiceModel();
        $billing = new TenantBillingService();

        CLI::write("\n=== Setup: a paid-tier Tenant, Seller-Pays end-to-end ===", 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => 'Success Fee Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'successfeetest', 'subscription_tier' => 'tsx_launch',
        ]);
        $this->assert($tenant['subscription_tier'] === 'tsx_launch', 'Tenant created on a paid tier');

        $seller = $partyModel->createParty('+919888802001');
        $buyer = $partyModel->createParty('+919888802002');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600009',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-SUCCESSFEE-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
            'fee_payer' => 'seller_pays',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 10000);
        $offer = (new OfferService())->submitOffer($saleEvent['id'], $buyer['id'], 95000);
        (new OfferService())->acceptOffer($saleEvent['id'], $offer['id'], null);
        $s = $settlementModel->findBySaleEvent($saleEvent['id']);

        $settlementService->confirmSellerNoc($s['id'], $seller['id']);
        $settlementService->confirmBuyerNoc($s['id'], $buyer['id']);
        $settlementService->submitRating($s['id'], $buyer['id'], 'buyer', 'good');
        $settlementService->submitRating($s['id'], $seller['id'], 'seller', 'good');

        $hold = $emdHoldModel->findBySaleEventAndParty($saleEvent['id'], $buyer['id']);
        $this->assert($hold['status'] === 'released', 'BR-32/33: Seller-Pays releases the buyer\'s EMD hold (not settled with a fee deduction)');
        $this->assert((float) $hold['forfeited_to_saas_amount'] === 0.0, 'BR-33: no fee is deducted from the buyer\'s EMD under Seller-Pays');

        $ledgerEntries = $ledgerModel->findForTenant($tenant['id']);
        $this->assert(count($ledgerEntries) === 1, 'BR-33/D-88: exactly one tenant_fee_ledger entry recorded for the Seller-Pays settlement');
        $this->assert($ledgerEntries[0]['status'] === 'unbilled', 'Ledger entry starts unbilled');
        $this->assert((float) $ledgerEntries[0]['amount'] === 1900.0, 'Ledger entry amount is the Success Fee: 2% of 95000 = 1900');

        $found = $invoices->findForSettlement($s['id']);
        $this->assert(count($found) === 1 && $found[0]['invoice_type'] === 'platform_to_seller', 'BR-56: one platform_to_seller invoice issued (Seller-Pays election)');

        CLI::write("\n=== D-88: monthly billing consolidation, tier-gated ===", 'yellow');
        $cocoTenant = $tenantModel->createTenant([
            'name' => 'CoCo Starter Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'cocostartertest', 'subscription_tier' => 'coco_starter',
        ]);
        // A CoCo Starter tenant should never actually have an unbilled
        // entry (blocked at election time in SaleEventController) — this
        // manually inserts one anyway to prove generateMonthlyInvoices()
        // defends against it independently, not just via the controller gate.
        $billing->recordUnbilledFee($cocoTenant['id'], $s['id'], $saleEvent['id'], 999.0);

        $generated = $billing->generateMonthlyInvoices();
        $this->assert(count($generated) === 1, 'Exactly one monthly invoice generated (the paid-tier tenant only)');
        $this->assert($generated[0]['tenant_id'] === $tenant['id'], 'The generated invoice belongs to the paid-tier tenant');
        $this->assert((float) $generated[0]['total_amount'] === 1900.0, 'Invoice total matches the single unbilled ledger entry (1900)');
        $this->assert((float) $generated[0]['gst_amount'] === 342.0, '18% GST on 1900 = 342');

        $cocoUnbilled = $ledgerModel->findUnbilledForTenant($cocoTenant['id']);
        $this->assert(count($cocoUnbilled) === 1 && $cocoUnbilled[0]['status'] === 'unbilled', 'BR-33/D-88: CoCo Starter tenant\'s entry is defensively skipped, left unbilled');

        $paidTenantLedger = $ledgerModel->findForTenant($tenant['id']);
        $this->assert($paidTenantLedger[0]['status'] === 'billed', 'The paid tenant\'s ledger entry is now marked billed');
        $this->assert($paidTenantLedger[0]['invoice_id'] === $generated[0]['id'], 'Ledger entry correctly links to the generated invoice');

        $secondRun = $billing->generateMonthlyInvoices();
        $this->assert(count($secondRun) === 0, 'A second call in the same period generates nothing further (idempotent — no unbilled entries left for the paid tenant)');

        CLI::write("\n=== D-88: marking a Tenant invoice paid ===", 'yellow');
        $invoiceId = $generated[0]['id'];
        $paidBy = $partyModel->createParty('+919888802099');
        $paid = $billing->markInvoicePaid($invoiceId, $paidBy['id']);
        $this->assert($paid['status'] === 'paid', 'Invoice status transitions to paid');
        $this->assert($paid['paid_by_party_id'] === $paidBy['id'], 'paid_by_party_id recorded');

        $alreadyPaidRejected = false;
        try {
            $billing->markInvoicePaid($invoiceId, $paidBy['id']);
        } catch (\RuntimeException $e) {
            $alreadyPaidRejected = str_contains($e->getMessage(), 'already');
        }
        $this->assert($alreadyPaidRejected, 'Marking an already-paid invoice paid again is rejected');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  ✓ {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  ✗ {$msg}", 'red');
        }
    }
}
