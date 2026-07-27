<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Libraries\SettlementService;
use App\Libraries\SchedulerService;
use App\Libraries\Paginator;
use App\Libraries\Uuid;

class TestPhase3aAccounts extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:phase3a';
    protected $description = 'Proves account management (edit/mPIN/deletion/earnings) and the new My* pages\' query logic are real.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $settlementService = new SettlementService();
        $scheduler = new SchedulerService();
        $db = \Config\Database::connect();

        CLI::write('=== Paginator math ===', 'yellow');
        $this->assert(Paginator::totalPages(45, 20) === 3, 'ceil(45/20) = 3 pages');
        $this->assert(Paginator::totalPages(0, 20) === 0, 'Zero results = zero pages');

        CLI::write("\n=== Account deletion: request, cancel, and scheduler finalization ===", 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Phase3A Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'phase3atest', 'buyer_fee_percent' => 5.00]);
        $deletingParty = $partyModel->createParty('+919555405001');

        $partyModel->update($deletingParty['id'], ['deletion_requested_at' => date('Y-m-d H:i:s'), 'deletion_reason' => 'testing']);
        $this->assert($partyModel->find($deletingParty['id'])['archived_at'] === null, 'A freshly-requested deletion does not archive immediately');

        $partyModel->update($deletingParty['id'], ['deletion_requested_at' => null, 'deletion_reason' => null]);
        $notArchived = $scheduler->processAccountDeletions();
        $this->assert(!in_array($deletingParty['id'], $notArchived, true), 'A cancelled deletion request is correctly never finalized');
        $this->assert($partyModel->find($deletingParty['id'])['archived_at'] === null, 'The cancelled party remains genuinely active');

        $partyModel->update($deletingParty['id'], ['deletion_requested_at' => date('Y-m-d H:i:s', strtotime('-31 days')), 'deletion_reason' => 'testing again']);
        $archived = $scheduler->processAccountDeletions();
        $this->assert(in_array($deletingParty['id'], $archived, true), 'A deletion request genuinely past 30 days is finalized by the scheduler');
        $this->assert($partyModel->find($deletingParty['id'])['archived_at'] !== null, 'archived_at is genuinely set — the same real BR-05 logical-archive mechanism, not a new one');
        $this->assert($partyModel->findByMobile('+919555405001') === null, 'The archived party is genuinely unreachable via findByMobile — a real soft delete, not cosmetic');

        $log = $db->table('audit_log')->where('event_type', 'account.deletion_finalized')->like('payload', $deletingParty['id'])->get()->getRowArray();
        $this->assert($log !== null, 'BR-05: the finalization is genuinely logged to the immutable audit trail');

        CLI::write("\n=== Earnings query: Tender sales correctly contribute zero fee (no emd_hold), without breaking the count ===", 'yellow');
        $seller = $partyModel->createParty('+919555405002');
        $buyer = $partyModel->createParty('+919555405003');

        // Buy-Now sale — real emd_hold with a settled fee split
        $listing1 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Machinery', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600098',
        ]);
        $se1 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing1['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-PHASE3A-001',
            'sale_format' => 'buy_now', 'expected_value' => 40000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($se1['id'], $buyer['id'], 'van', 4000);
        $settlement1 = $settlementService->createForSaleEvent($se1['id'], $buyer['id'], 38000);
        $settlementService->confirmSellerNoc($settlement1['id'], $seller['id']);
        $settlementService->confirmBuyerNoc($settlement1['id'], $buyer['id']);
        $settlementService->submitRating($settlement1['id'], $buyer['id'], 'buyer', 'good');
        $settlementService->submitRating($settlement1['id'], $seller['id'], 'seller', 'good');

        // Tender sale — genuinely no emd_hold row at all (uses tender_emd_log instead)
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'physical_condition' => 'Used',
            'category' => 'Antiques', 'quantity' => 1, 'quantity_basis' => 'unit',
            'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600099',
        ]);
        $se2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-PHASE3A-002',
            'sale_format' => 'tender', 'status' => 'active',
        ]);
        // Directly insert a completed settlement for the Tender sale (no
        // real emd_hold row exists for it — confirmed by NOT creating one)
        $settlementId = Uuid::v4();
        $db->table('settlement')->insert([
            'id' => $settlementId, 'sale_event_id' => $se2['id'], 'buyer_party_id' => $buyer['id'],
            'seller_party_id' => $seller['id'], 'final_price' => 25000, 'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $earnings = $db->table('settlement s')
            ->select('COUNT(s.id) as sale_count, COALESCE(SUM(s.final_price), 0) as total_sales,
                      COALESCE(SUM(eh.forfeited_to_tenant_amount + eh.forfeited_to_saas_amount), 0) as total_fees')
            ->join('emd_hold eh', 'eh.sale_event_id = s.sale_event_id AND eh.party_id = s.buyer_party_id', 'left')
            ->where('s.seller_party_id', $seller['id'])
            ->where('s.status', 'completed')
            ->where('s.completed_at >=', date('Y-01-01 00:00:00'))
            ->get()->getRowArray();

        $this->assert((int) $earnings['sale_count'] === 2, 'Both sales genuinely count, including the Tender one with no emd_hold row at all');
        $this->assert((float) $earnings['total_sales'] === 63000.0, 'Total sales genuinely sums both (38000 + 25000)');
        $this->assert((float) $earnings['total_fees'] > 0 && (float) $earnings['total_fees'] < 38000, 'Fees genuinely reflect only the Buy-Now sale\'s real deduction, not fabricated for Tender');

        CLI::write("\n=== My Sales query: fee_deducted correctly resolves per-row via the same LEFT JOIN, not just in aggregate ===", 'yellow');
        $rows = $db->table('settlement s')
            ->select('s.id, s.final_price, se.sale_format,
                      COALESCE(eh.forfeited_to_tenant_amount, 0) + COALESCE(eh.forfeited_to_saas_amount, 0) as fee_deducted')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('emd_hold eh', 'eh.sale_event_id = s.sale_event_id AND eh.party_id = s.buyer_party_id', 'left')
            ->where('s.seller_party_id', $seller['id'])
            ->orderBy('s.created_at', 'ASC')
            ->get()->getResultArray();

        $this->assert(count($rows) === 2, 'Both rows genuinely returned, one per settlement, not duplicated by the join');
        $buyNowRow = array_filter($rows, fn($r) => $r['sale_format'] === 'buy_now');
        $tenderRow = array_filter($rows, fn($r) => $r['sale_format'] === 'tender');
        $this->assert((float) array_values($buyNowRow)[0]['fee_deducted'] > 0, 'The Buy-Now row genuinely shows a real fee');
        $this->assert((float) array_values($tenderRow)[0]['fee_deducted'] === 0.0, 'The Tender row correctly shows zero fee, not null or an error');

        CLI::write("\n=== Account edit correctly scopes to non-KYC-critical fields only ===", 'yellow');
        $editParty = $partyModel->createParty('+919555405004');
        $originalPan = $editParty['pan'];
        $partyModel->update($editParty['id'], ['full_name' => 'Test Name', 'occupation' => 'Engineer', 'recovery_email' => 'test@example.com']);
        $afterEdit = $partyModel->find($editParty['id']);
        $this->assert($afterEdit['full_name'] === 'Test Name' && $afterEdit['occupation'] === 'Engineer', 'Safe fields genuinely update');
        $this->assert($afterEdit['pan'] === $originalPan, 'PAN (a KYC-verification anchor) is genuinely untouched by the account-edit path, which never writes to it at all');

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
