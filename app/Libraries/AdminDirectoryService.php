<?php

namespace App\Libraries;

use App\Models\TenantModel;

// D-106: "Lot Directory" and "Trading Session Directory" — the
// Custodian (Super Admin) had no way to browse every listing/sale
// event platform-wide across every Tenant; only per-tenant pending
// queues existed anywhere. Pulled out of AdminController into its own
// service so the filtering logic is directly testable without needing
// a real TOTP-based Super Admin HTTP login just to exercise it.
class AdminDirectoryService
{
    public function findListings(?string $q, ?string $tenantId, ?string $format, ?string $status, int $limit, int $offset): array
    {
        return $this->listingQuery($q, $tenantId, $format, $status)
            ->orderBy('l.created_at', 'DESC')->limit($limit, $offset)->get()->getResultArray();
    }

    public function countListings(?string $q, ?string $tenantId, ?string $format, ?string $status): int
    {
        return $this->listingQuery($q, $tenantId, $format, $status)->countAllResults(false);
    }

    private function listingQuery(?string $q, ?string $tenantId, ?string $format, ?string $status)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('listing l')
            ->select('l.id, l.category, l.subcategory, l.status, l.view_count, l.created_at,
                      t.id as tenant_id, t.name as tenant_name,
                      se.id as sale_event_id, se.sale_format, se.status as sale_status')
            ->join('tenant t', 't.id = l.tenant_id')
            ->join('sale_event se', 'se.listing_id = l.id', 'left');
        if ($q) $builder->groupStart()->like('l.category', $q)->orLike('l.subcategory', $q)->orLike('t.name', $q)->groupEnd();
        if ($tenantId) $builder->where('l.tenant_id', $tenantId);
        if ($format) $builder->where('se.sale_format', $format);
        if ($status) $builder->where('l.status', $status);
        return $builder;
    }

    public function findSaleEvents(?string $tenantId, ?string $format, ?string $status, int $limit, int $offset): array
    {
        return $this->saleEventQuery($tenantId, $format, $status)
            ->orderBy('se.created_at', 'DESC')->limit($limit, $offset)->get()->getResultArray();
    }

    public function countSaleEvents(?string $tenantId, ?string $format, ?string $status): int
    {
        return $this->saleEventQuery($tenantId, $format, $status)->countAllResults(false);
    }

    private function saleEventQuery(?string $tenantId, ?string $format, ?string $status)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('sale_event se')
            ->select('se.id, se.ern, se.sale_format, se.status, se.current_price, se.reserve_value, se.expected_value, se.created_at,
                      t.id as tenant_id, t.name as tenant_name, l.category')
            ->join('tenant t', 't.id = se.tenant_id')
            ->join('listing l', 'l.id = se.listing_id');
        if ($tenantId) $builder->where('se.tenant_id', $tenantId);
        if ($format) $builder->where('se.sale_format', $format);
        if ($status) $builder->where('se.status', $status);
        return $builder;
    }
}
