<?php

namespace App\Libraries;

class InvoiceService
{
    private const GST_RATE_PERCENT = 18.0;

    public function generateForSettlement(string $settlementId, array $settlement, array $tenant, float $tenantAmount, float $saasAmount): array
    {
        $db = \Config\Database::connect();

        $buyer = (new \App\Models\PartyModel())->find($settlement['buyer_party_id']);
        $tenantInvoice = $this->createInvoice($db, $settlementId, 'tenant_to_buyer',
            $tenant['name'], $buyer['mobile_number'] ?? 'Buyer', $tenantAmount
        );

        $saasInvoice = $this->createInvoice($db, $settlementId, 'saas_to_tenant',
            'eBid Hub (SaaS)', $tenant['name'], $saasAmount
        );

        (new AuditLogService())->log('invoice.generated', null, [
            'settlementId' => $settlementId,
            'tenantInvoiceNumber' => $tenantInvoice['invoice_number'],
            'saasInvoiceNumber' => $saasInvoice['invoice_number'],
        ]);

        return ['tenantInvoice' => $tenantInvoice, 'saasInvoice' => $saasInvoice];
    }

    private function createInvoice($db, string $settlementId, string $type, string $issuedBy, string $issuedTo, float $baseAmount): array
    {
        $gstAmount = round($baseAmount * (self::GST_RATE_PERCENT / 100), 2);
        $id = Uuid::v4();
        $invoiceNumber = 'INV-' . strtoupper(substr($type, 0, 3)) . '-' . date('Ymd') . '-' . strtoupper(substr($id, 0, 8));

        $db->table('invoice')->insert([
            'id' => $id,
            'settlement_id' => $settlementId,
            'invoice_type' => $type,
            'invoice_number' => $invoiceNumber,
            'issued_by_name' => $issuedBy,
            'issued_to_name' => $issuedTo,
            'base_amount' => $baseAmount,
            'gst_rate_percent' => self::GST_RATE_PERCENT,
            'gst_amount' => $gstAmount,
            'total_amount' => round($baseAmount + $gstAmount, 2),
        ]);

        return $db->table('invoice')->where('id', $id)->get()->getRowArray();
    }

    public function findForSettlement(string $settlementId): array
    {
        $db = \Config\Database::connect();
        return $db->table('invoice')->where('settlement_id', $settlementId)->get()->getResultArray();
    }
}
