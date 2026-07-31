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
use App\Libraries\InvoiceService;

// BR-56: invoice history (findForParty/countForParty/findIfAuthorized)
// against a real, fully-completed settlement — not a separate mock
// invoice schema.
class TestInvoices extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:invoices';
    protected $description = 'Runs BR-56 invoice history/authorization against a real completed settlement.';

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
        $settlementService = new SettlementService();
        $invoices = new InvoiceService();

        CLI::write('=== Setup: a fully-completed Buy-Now settlement ===', 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => 'Invoice Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'invoicetest',
        ]);
        $seller = $partyModel->createParty('+919889901001');
        $buyer = $partyModel->createParty('+919889901002');
        $stranger = $partyModel->createParty('+919889901003');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600007',
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-INVOICE-001',
            'sale_format' => 'buy_now', 'expected_value' => 100000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $buyer['id'], 'van', 10000);
        $offer = $offers->submitOffer($saleEvent['id'], $buyer['id'], 95000);
        $offers->acceptOffer($saleEvent['id'], $offer['id'], null);
        $s = $settlementModel->findBySaleEvent($saleEvent['id']);

        $settlementService->confirmSellerNoc($s['id'], $seller['id']);
        $settlementService->confirmBuyerNoc($s['id'], $buyer['id']);
        $settlementService->submitRating($s['id'], $buyer['id'], 'buyer', 'good');
        $settlementService->submitRating($s['id'], $seller['id'], 'seller', 'good');

        // BR-56/31/32 (D-87/D-88): one platform invoice per settlement now,
        // issued to whichever party paid the Success Fee -- the default
        // Buyer-Pays election here means the buyer.
        $found = $invoices->findForSettlement($s['id']);
        $this->assert(count($found) === 1, 'Exactly one platform_to_buyer invoice generated on completion (Buyer-Pays default)');

        CLI::write("\n=== findForParty: buyer and seller both see the platform_to_buyer invoice ===", 'yellow');
        $buyerInvoices = $invoices->findForParty($buyer['id'], 20, 0);
        $this->assert(count($buyerInvoices) === 1, 'Buyer sees exactly 1 invoice');
        $this->assert($buyerInvoices[0]['invoice_type'] === 'platform_to_buyer', 'Buyer sees the platform_to_buyer invoice');

        $sellerInvoices = $invoices->findForParty($seller['id'], 20, 0);
        $this->assert(count($sellerInvoices) === 1, 'Seller also sees the same platform_to_buyer invoice (their sale\'s Success Fee)');

        $this->assert($invoices->countForParty($buyer['id']) === 1, 'countForParty matches findForParty count');

        $strangerInvoices = $invoices->findForParty($stranger['id'], 20, 0);
        $this->assert(count($strangerInvoices) === 0, 'An unrelated party sees no invoices at all');

        CLI::write("\n=== findIfAuthorized: only a genuine party to the settlement may access one invoice ===", 'yellow');
        $invoiceId = $buyerInvoices[0]['id'];
        $authorizedForBuyer = $invoices->findIfAuthorized($invoiceId, $buyer['id']);
        $this->assert($authorizedForBuyer !== null, 'Buyer is authorized to view their own invoice');
        $authorizedForSeller = $invoices->findIfAuthorized($invoiceId, $seller['id']);
        $this->assert($authorizedForSeller !== null, 'Seller is authorized to view the invoice for their own sale');
        $authorizedForStranger = $invoices->findIfAuthorized($invoiceId, $stranger['id']);
        $this->assert($authorizedForStranger === null, 'An unrelated party is correctly denied — not just hidden from the list, actually blocked');

        CLI::write("\n=== Real PDF bytes generated, not just an HTML string ===", 'yellow');
        $html = view('account/invoice_pdf', ['invoice' => $authorizedForBuyer]);
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfBytes = $dompdf->output();
        $this->assert(str_starts_with($pdfBytes, '%PDF-'), 'Output is a genuine PDF file (starts with %PDF- magic bytes)');
        $this->assert(strlen($pdfBytes) > 1000, 'PDF output has real, non-trivial content (' . strlen($pdfBytes) . ' bytes)');

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
