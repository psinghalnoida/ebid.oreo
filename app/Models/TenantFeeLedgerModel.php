<?php

namespace App\Models;

use CodeIgniter\Model;

// BR-32/33 (D-87/D-88): one row per Seller-Pays settlement's Success Fee,
// unbilled until TenantBillingService consolidates it into a monthly
// tenant_monthly_invoice. See TenantBillingService for the full mechanism.
class TenantFeeLedgerModel extends Model
{
    protected $table            = 'tenant_fee_ledger';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'tenant_id', 'settlement_id', 'sale_event_id', 'amount', 'status', 'invoice_id',
    ];

    public function recordEntry(string $tenantId, string $settlementId, string $saleEventId, float $amount): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'settlement_id' => $settlementId,
            'sale_event_id' => $saleEventId, 'amount' => $amount, 'status' => 'unbilled',
        ]);
        return $this->find($id);
    }

    public function findUnbilledForTenant(string $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->where('status', 'unbilled')->findAll();
    }

    public function findDistinctUnbilledTenantIds(): array
    {
        return $this->distinct()->select('tenant_id')->where('status', 'unbilled')->findAll();
    }

    public function markBilled(array $ledgerIds, string $invoiceId): void
    {
        if (empty($ledgerIds)) {
            return;
        }
        $this->whereIn('id', $ledgerIds)->set(['status' => 'billed', 'invoice_id' => $invoiceId])->update();
    }

    public function findForTenant(string $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('created_at', 'DESC')->findAll();
    }
}
