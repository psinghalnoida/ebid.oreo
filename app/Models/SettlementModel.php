<?php

namespace App\Models;

use CodeIgniter\Model;

class SettlementModel extends Model
{
    protected $table            = 'settlement';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'buyer_party_id', 'seller_party_id', 'final_price',
        'seller_noc_confirmed_at', 'buyer_noc_confirmed_at',
        'buyer_rated_seller_at', 'seller_rated_buyer_at',
        'status', 'stall_flagged_at', 'forced_neutral_applied_at', 'completed_at',
    ];

    public function createSettlement(string $saleEventId, string $buyerId, string $sellerId, float $finalPrice): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId,
            'buyer_party_id' => $buyerId, 'seller_party_id' => $sellerId,
            'final_price' => $finalPrice, 'status' => 'pending',
        ]);
        return $this->find($id);
    }

    public function findBySaleEvent(string $saleEventId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)->first();
    }

    // BR-39: settlements that have sat incomplete past the threshold and
    // haven't already been flagged
    public function findStalledCandidates(string $olderThanDate): array
    {
        return $this->where('status', 'pending')
            ->where('created_at <', $olderThanDate)
            ->where('stall_flagged_at', null)
            ->findAll();
    }
}
