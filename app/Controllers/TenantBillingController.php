<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\TenantFeeLedgerModel;
use App\Models\TenantMonthlyInvoiceModel;
use App\Libraries\TenantBillingService;

// BR-32/33 (D-87/D-88): the Tenant-facing and SaaS-Admin-facing sides of
// the monthly billing mechanism that collects Seller-Pays Success Fees —
// see TenantBillingService for the full design rationale.
class TenantBillingController extends BaseController
{
    // Tenant Admin's own view of their unbilled ledger and past invoices.
    public function forTenant(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('tenant_admin/billing', [
            'title' => 'Billing — ' . $tenant['name'],
            'tenant' => $tenant,
            'unbilled' => (new TenantFeeLedgerModel())->findUnbilledForTenant($tenantId),
            'invoices' => (new TenantMonthlyInvoiceModel())->findForTenant($tenantId),
        ]);
    }

    // SaaS Admin's cross-tenant view of every pending monthly invoice.
    public function index()
    {
        return view('admin/tenant_invoices', [
            'title' => 'Tenant Monthly Invoices — eBid Hub',
            'pending' => (new TenantMonthlyInvoiceModel())->findAllPending(),
        ]);
    }

    // Marking an invoice paid is a manual SaaS Admin action — no
    // automated dunning/suspension exists yet (flagged in
    // TenantBillingService, not silently skipped).
    public function markPaid(string $invoiceId)
    {
        $partyId = session()->get('super_admin_party_id');
        try {
            (new TenantBillingService())->markInvoicePaid($invoiceId, $partyId);
        } catch (\RuntimeException $e) {
            return redirect()->to('/admin/tenant-invoices')->with('error', $e->getMessage());
        }
        return redirect()->to('/admin/tenant-invoices')->with('error', 'Invoice marked paid.');
    }
}
