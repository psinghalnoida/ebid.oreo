<?php

namespace App\Models;

use CodeIgniter\Model;

class OfferModel extends Model
{
    protected $table            = 'offer';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'buyer_party_id', 'amount', 'status',
        'seller_selection_reason', 'withdrawal_reason', 'decided_at', 'withdrawn_at',
    ];

    public function createOffer(string $saleEventId, string $buyerPartyId, float $amount): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId,
            'buyer_party_id' => $buyerPartyId, 'amount' => $amount, 'status' => 'submitted',
        ]);
        return $this->find($id);
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->orderBy('amount', 'DESC')
            ->findAll();
    }

    public function findHighestSubmitted(string $saleEventId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->where('status', 'submitted')
            ->orderBy('amount', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->first();
    }

    // BR-42: the reason is mandatory only when this ISN'T the highest offer
    public function markAccepted(string $offerId, ?string $reason = null): array
    {
        $this->update($offerId, [
            'status' => 'accepted', 'seller_selection_reason' => $reason, 'decided_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($offerId);
    }

    public function markRejected(string $offerId): array
    {
        $this->update($offerId, ['status' => 'rejected', 'decided_at' => date('Y-m-d H:i:s')]);
        return $this->find($offerId);
    }

    public function markWithdrawn(string $offerId, ?string $reason = null): array
    {
        $this->update($offerId, [
            'status' => 'withdrawn', 'withdrawal_reason' => $reason, 'withdrawn_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($offerId);
    }

    // BR: offers lapse unactioned after 3 days, no reason required
    public function findStaleSubmitted(string $olderThanDate): array
    {
        return $this->where('status', 'submitted')
            ->where('created_at <', $olderThanDate)
            ->findAll();
    }

    public function markLapsed(string $offerId): array
    {
        $this->update($offerId, ['status' => 'lapsed', 'decided_at' => date('Y-m-d H:i:s')]);
        return $this->find($offerId);
    }

    public function rejectAllOtherSubmitted(string $saleEventId, string $exceptOfferId): void
    {
        $this->where('sale_event_id', $saleEventId)
            ->where('status', 'submitted')
            ->where('id !=', $exceptOfferId)
            ->set(['status' => 'rejected', 'decided_at' => date('Y-m-d H:i:s')])
            ->update();
    }
}
