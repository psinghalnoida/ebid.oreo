<?php

namespace App\Libraries;

class InvoiceService
{
    private const GST_RATE_PERCENT = 18.0;
    private const PLATFORM_NAME = 'TradeSphereX (ADWITIX)';

    // BR-56/BR-31/32 (D-87/D-88): the Success Fee is 100% platform
    // revenue now — one GST-compliant invoice per settlement, issued
    // directly by the platform to whichever party paid it under that
    // session's Fee Payer Election (the Trader under Buyer-Pays, the
    // Market Maker under Seller-Pays). Replaces the old two-invoice
    // tenant_to_buyer/saas_to_tenant split, which no longer has a real
    // counterpart now that the Tenant doesn't share in the fee.
    public function generateForSettlement(string $settlementId, array $settlement, float $feeAmount, string $feePayer): array
    {
        $db = \Config\Database::connect();

        if ($feePayer === 'seller_pays') {
            $seller = (new \App\Models\PartyModel())->find($settlement['seller_party_id']);
            $invoice = $this->createInvoice($db, $settlementId, 'platform_to_seller',
                self::PLATFORM_NAME, $seller['mobile_number'] ?? 'Market Maker', $feeAmount
            );
        } else {
            $buyer = (new \App\Models\PartyModel())->find($settlement['buyer_party_id']);
            $invoice = $this->createInvoice($db, $settlementId, 'platform_to_buyer',
                self::PLATFORM_NAME, $buyer['mobile_number'] ?? 'Trader', $feeAmount
            );
        }

        (new AuditLogService())->log('invoice.generated', null, [
            'settlementId' => $settlementId, 'feePayer' => $feePayer, 'invoiceNumber' => $invoice['invoice_number'],
        ]);

        return ['invoice' => $invoice];
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
    // for. Either party to a settlement can see whichever fee invoice was
    // actually issued for it — 'tenant_to_buyer' is the pre-D-87 legacy
    // type kept for historical invoices; 'platform_to_buyer'/
    // 'platform_to_seller' are the current ones (D-87/D-88). The old
    // 'saas_to_tenant' type is platform-internal and correctly excluded.
    private const USER_FACING_INVOICE_TYPES = ['tenant_to_buyer', 'platform_to_buyer', 'platform_to_seller'];

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
            ->whereIn('i.invoice_type', self::USER_FACING_INVOICE_TYPES)
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
            ->whereIn('i.invoice_type', self::USER_FACING_INVOICE_TYPES)
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
