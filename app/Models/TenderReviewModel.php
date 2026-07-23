<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderReviewModel extends Model
{
    protected $table            = 'tender_review';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'bid_id', 'party_id', 'round_number', 'status',
        'extension_reason', 'extension_granted_by_party_id', 'extension_granted_at',
        'rejection_reason', 'rejected_by_party_id', 'rejected_at',
        'confirmed_by_party_id', 'confirmed_at',
    ];

    public function createReview(string $saleEventId, string $bidId, string $partyId, int $roundNumber): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId, 'bid_id' => $bidId,
            'party_id' => $partyId, 'round_number' => $roundNumber, 'status' => 'provisional',
        ]);
        return $this->find($id);
    }

    public function findCurrentForSaleEvent(string $saleEventId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->orderBy('round_number', 'DESC')
            ->first();
    }

    public function findAllForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->orderBy('round_number', 'ASC')->findAll();
    }
}
