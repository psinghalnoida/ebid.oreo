<?php

namespace App\Libraries;

use App\Models\TenantFeeLedgerModel;
use App\Models\TenantMonthlyInvoiceModel;
use App\Models\TenantModel;

// BR-08/BR-32/BR-33 (D-87/D-88): Seller-Pays has no real-time collection
// mechanism — the platform never touches the seller's 100% sale-value
// proceeds (BR-33), so a Seller-Pays Success Fee cannot be deducted at
// settlement the way Buyer-Pays is. Resolved per the project owner's own
// direction: every Seller-Pays fee is recorded as an unbilled
// tenant_fee_ledger entry at settlement, then consolidated into one
// GST-compliant invoice per Tenant per calendar month
// (generateMonthlyInvoices, wired into SchedulerService::runAll()).
// Restricted to non-CoCo-Starter tenants — a CoCo Starter tenant has no
// ongoing billing relationship to invoice against (Section 5.2), so
// Seller-Pays is not offered to them at all (enforced at election time in
// SaleEventController, defended again here).
//
// No automated dunning/suspension exists for an unpaid monthly invoice —
// intentionally out of scope for this build (flagged, not silently
// skipped); marking an invoice paid is a manual SaaS Admin action.
class TenantBillingService
{
    private const GST_RATE_PERCENT = 18.0;

    private TenantFeeLedgerModel $ledgerModel;
    private TenantMonthlyInvoiceModel $invoiceModel;
    private TenantModel $tenantModel;

    public function __construct()
    {
        $this->ledgerModel = new TenantFeeLedgerModel();
        $this->invoiceModel = new TenantMonthlyInvoiceModel();
        $this->tenantModel = new TenantModel();
    }

    public function recordUnbilledFee(string $tenantId, string $settlementId, string $saleEventId, float $amount): array
    {
        $entry = $this->ledgerModel->recordEntry($tenantId, $settlementId, $saleEventId, $amount);
        (new AuditLogService())->log('tenant_fee_ledger.recorded', null, [
            'tenantId' => $tenantId, 'settlementId' => $settlementId, 'saleEventId' => $saleEventId, 'amount' => $amount,
        ]);
        return $entry;
    }

    // Consolidates every unbilled ledger entry, per Tenant, into one
    // GST-compliant monthly invoice covering the prior calendar month.
    // Callable on demand for testing; production cadence is monthly via
    // SchedulerService::runAll(). Idempotent in practice — a second call
    // within the same month simply finds no unbilled entries left for
    // tenants already invoiced.
    public function generateMonthlyInvoices(?\DateTimeImmutable $asOf = null): array
    {
        $asOf ??= new \DateTimeImmutable();
        $periodStart = $asOf->modify('first day of last month')->setTime(0, 0, 0);
        $periodEnd = $asOf->modify('first day of this month')->setTime(0, 0, 0);

        $tenantIds = array_unique(array_column($this->ledgerModel->findDistinctUnbilledTenantIds(), 'tenant_id'));
        $generated = [];
        foreach ($tenantIds as $tenantId) {
            $tenant = $this->tenantModel->find($tenantId);
            if (!$tenant || $tenant['subscription_tier'] === 'coco_starter') {
                // BR-33/D-88: a CoCo Starter tenant should never have
                // unbilled Seller-Pays entries at all (blocked at
                // election time) — skipped defensively rather than
                // silently invoicing a tier that was never meant to
                // carry this liability.
                continue;
            }
            $entries = $this->ledgerModel->findUnbilledForTenant($tenantId);
            if (empty($entries)) {
                continue;
            }
            $totalAmount = round(array_sum(array_column($entries, 'amount')), 2);
            $gstAmount = round($totalAmount * (self::GST_RATE_PERCENT / 100), 2);
            $invoiceNumber = 'TMI-' . strtoupper(substr($tenantId, 0, 8)) . '-' . $asOf->format('Ym');

            $invoice = $this->invoiceModel->createInvoice([
                'tenant_id' => $tenantId,
                'invoice_number' => $invoiceNumber,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
                'total_amount' => $totalAmount,
                'gst_amount' => $gstAmount,
            ]);
            $this->ledgerModel->markBilled(array_column($entries, 'id'), $invoice['id']);

            (new AuditLogService())->log('tenant_monthly_invoice.generated', null, [
                'tenantId' => $tenantId, 'invoiceId' => $invoice['id'], 'totalAmount' => $totalAmount, 'entryCount' => count($entries),
            ]);
            $generated[] = $invoice;
        }
        return $generated;
    }

    public function markInvoicePaid(string $invoiceId, string $paidByPartyId): array
    {
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            throw new \RuntimeException('Tenant monthly invoice not found.');
        }
        if ($invoice['status'] === 'paid') {
            throw new \RuntimeException('This invoice has already been marked paid.');
        }
        $updated = $this->invoiceModel->markPaid($invoiceId, $paidByPartyId);
        (new AuditLogService())->log('tenant_monthly_invoice.marked_paid', $paidByPartyId, [
            'invoiceId' => $invoiceId, 'tenantId' => $invoice['tenant_id'],
        ]);
        return $updated;
    }
}
