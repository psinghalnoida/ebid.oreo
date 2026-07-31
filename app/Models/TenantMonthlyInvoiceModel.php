<?php

namespace App\Models;

use CodeIgniter\Model;

// BR-32/33 (D-87/D-88): the actual monthly bill a non-CoCo-Starter
// Tenant receives, consolidating every Seller-Pays Success Fee accrued
// in tenant_fee_ledger during that calendar month. See
// TenantBillingService::generateMonthlyInvoices().
class TenantMonthlyInvoiceModel extends Model
{
    protected $table            = 'tenant_monthly_invoice';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'tenant_id', 'invoice_number', 'period_start', 'period_end',
        'total_amount', 'gst_amount', 'status', 'paid_at', 'paid_by_party_id',
    ];

    public function createInvoice(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function findForTenant(string $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('generated_at', 'DESC')->findAll();
    }

    public function markPaid(string $invoiceId, string $paidByPartyId): array
    {
        $this->update($invoiceId, [
            'status' => 'paid', 'paid_at' => date('Y-m-d H:i:s'), 'paid_by_party_id' => $paidByPartyId,
        ]);
        return $this->find($invoiceId);
    }

    public function findAllPending(): array
    {
        return $this->where('status', 'pending')->orderBy('generated_at', 'DESC')->findAll();
    }
}
