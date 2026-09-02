<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\TenantFeeLedgerModel;
use App\Models\TenantMonthlyInvoiceModel;
use App\Libraries\TenantBillingService;
use App\Libraries\UserAuthContext;

// BR-32/33 (D-87/D-88): the Tenant-facing and SaaS-Admin-facing sides of
// the monthly billing mechanism that collects Seller-Pays Success Fees.
class TenantBillingController extends BaseController
{
    // Tenant Admin's own view of their unbilled ledger and past invoices.
    // jwtTenantAdmin-gated (resource type 'tenant').
    public function forTenant(string $tenantId)
    {
        $tenant = (new TenantModel())->find($tenantId);
        if (!$tenant) {
            return $this->jsonError(404, 'not_found', 'Tenant not found.');
        }

        return $this->apiResponse([
            'tenant' => $tenant,
            'unbilled' => (new TenantFeeLedgerModel())->findUnbilledForTenant($tenantId),
            'invoices' => (new TenantMonthlyInvoiceModel())->findForTenant($tenantId),
        ]);
    }

    // SaaS Admin's cross-tenant view of every pending monthly invoice.
    // jwtSuperAdmin-gated.
    public function index()
    {
        return $this->apiResponse(['pending' => (new TenantMonthlyInvoiceModel())->findAllPending()]);
    }

    // Marking an invoice paid is a manual SaaS Admin action — no
    // automated dunning/suspension exists yet. jwtSuperAdmin-gated.
    public function markPaid(string $invoiceId)
    {
        try {
            (new TenantBillingService())->markInvoicePaid($invoiceId, UserAuthContext::partyId());
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'mark_paid_failed', $e->getMessage());
        }
        return $this->apiResponse(['message' => 'Invoice marked paid.']);
    }
}
