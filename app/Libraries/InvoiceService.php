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

    // BR-56: a party only ever has a legitimate view of an invoice if
    // they were the buyer or seller on the settlement it was generated
    // for — a buyer sees the tenant_to_buyer invoice billed to them, a
    // seller sees the commission invoice deducted from their sale.
    // saas_to_tenant invoices are platform-internal (Tenant/SaaS only,
    // not buyer/seller-facing) and are correctly excluded here.
    public function findForParty(string $partyId, int $limit, int $offset): array
    {
        $db = \Config\Database::connect();
        return $db->table('invoice i')
            ->select('i.*, s.final_price, s.sale_event_id, se.sale_format, se.ern')
            ->join('settlement s', 's.id = i.settlement_id')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->groupStart()
                ->where('s.buyer_party_id', $partyId)
                ->orWhere('s.seller_party_id', $partyId)
            ->groupEnd()
            ->where('i.invoice_type', 'tenant_to_buyer')
            ->orderBy('i.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->getResultArray();
    }

    public function countForParty(string $partyId): int
    {
        $db = \Config\Database::connect();
        return $db->table('invoice i')
            ->join('settlement s', 's.id = i.settlement_id')
            ->groupStart()
                ->where('s.buyer_party_id', $partyId)
                ->orWhere('s.seller_party_id', $partyId)
            ->groupEnd()
            ->where('i.invoice_type', 'tenant_to_buyer')
            ->countAllResults();
    }

    // Authorization check for a single invoice: only the buyer/seller on
    // the underlying settlement (or a platform admin, checked by the
    // caller) may view/download it.
    public function findIfAuthorized(string $invoiceId, string $partyId): ?array
    {
        $db = \Config\Database::connect();
        return $db->table('invoice i')
            ->select('i.*, s.final_price, s.buyer_party_id, s.seller_party_id, s.sale_event_id, se.sale_format, se.ern, l.category')
            ->join('settlement s', 's.id = i.settlement_id')
            ->join('sale_event se', 'se.id = s.sale_event_id')
            ->join('listing l', 'l.id = se.listing_id')
            ->where('i.id', $invoiceId)
            ->groupStart()
                ->where('s.buyer_party_id', $partyId)
                ->orWhere('s.seller_party_id', $partyId)
            ->groupEnd()
            ->get()->getRowArray();
    }
}
