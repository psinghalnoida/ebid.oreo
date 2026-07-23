<?php

namespace App\Models;

use CodeIgniter\Model;

class SaleEventModel extends Model
{
    protected $table            = 'sale_event';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'listing_id', 'tenant_id', 'ern', 'sale_format', 'status',
        'expected_value', 'reserve_value', 'result_mode',
        'current_price', 'current_high_bidder_party_id',
        'grace_period_ends_at', 'scheduled_start_at', 'scheduled_end_at',
        'dynamic_time_trigger_minutes', 'dynamic_time_extension_minutes',
        'bid_increment_amount', 'increment_halved_at', 'anti_snipe_trigger_minutes',
        'tender_increment', 'tender_increment_halved', 'tender_increment_halving_minutes',
        'actual_closed_at', 'rejection_reason',
        'emergency_stopped_at', 'emergency_stop_reason', 'updated_at',
    ];

    public function createSaleEvent(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function updateCurrentPrice(string $saleEventId, float $price, string $highBidderPartyId): array
    {
        $this->update($saleEventId, [
            'current_price' => $price,
            'current_high_bidder_party_id' => $highBidderPartyId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($saleEventId);
    }

    public function transitionStatus(string $saleEventId, string $status): array
    {
        $this->update($saleEventId, ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        return $this->find($saleEventId);
    }

    public function markClosed(string $saleEventId, string $status): array
    {
        $this->update($saleEventId, [
            'status' => $status,
            'actual_closed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($saleEventId);
    }
}
