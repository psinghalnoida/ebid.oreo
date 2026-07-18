<?php

namespace App\Models;

use CodeIgniter\Model;

class BidModel extends Model
{
    protected $table            = 'bid';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'bidder_party_id', 'amount', 'standing',
        'topup_required_by', 'topup_paid_at', 'defaulted_at',
    ];

    public function createBid(string $saleEventId, string $bidderPartyId, float $amount): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id,
            'sale_event_id' => $saleEventId,
            'bidder_party_id' => $bidderPartyId,
            'amount' => $amount,
            'standing' => 'outbid',
        ]);
        return $this->find($id);
    }

    // BR-43: current high bid — needed to enforce the 150% anti-jacking ceiling
    public function findCurrentHighBid(string $saleEventId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->orderBy('amount', 'DESC')
            ->orderBy('placed_at', 'ASC')
            ->first();
    }

    // BR-28: ranked standings for cascade — H1, H2, H3 in order
    public function findRankedBids(string $saleEventId, int $limit = 3): array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->orderBy('amount', 'DESC')
            ->orderBy('placed_at', 'ASC')
            ->findAll($limit);
    }

    public function setStanding(string $bidId, string $standing): array
    {
        $this->update($bidId, ['standing' => $standing]);
        return $this->find($bidId);
    }

    public function setTopupWindow(string $bidId, string $topupRequiredBy): array
    {
        $this->update($bidId, ['topup_required_by' => $topupRequiredBy]);
        return $this->find($bidId);
    }

    public function markTopupPaid(string $bidId): array
    {
        $this->update($bidId, ['topup_paid_at' => date('Y-m-d H:i:s'), 'standing' => 'h1']);
        return $this->find($bidId);
    }

    public function markDefaulted(string $bidId): array
    {
        $this->update($bidId, ['defaulted_at' => date('Y-m-d H:i:s'), 'standing' => 'defaulted']);
        return $this->find($bidId);
    }

    public function resetOutbidStandings(string $saleEventId, array $exceptBidIds = []): void
    {
        $builder = $this->where('sale_event_id', $saleEventId)
            ->whereNotIn('standing', ['defaulted', 'withdrawn']);
        if (!empty($exceptBidIds)) {
            $builder = $builder->whereNotIn('id', $exceptBidIds);
        }
        $builder->set('standing', 'outbid')->update();
    }

    // BR-14: cancellation/edit flows withdraw all active bids
    public function withdrawAllForSaleEvent(string $saleEventId): void
    {
        $this->where('sale_event_id', $saleEventId)
            ->whereNotIn('standing', ['defaulted', 'withdrawn'])
            ->set('standing', 'withdrawn')
            ->update();
    }
}
