<?php

namespace App\Models;

use CodeIgniter\Model;

class EmdHoldModel extends Model
{
    protected $table            = 'emd_hold';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'party_id', 'channel', 'amount', 'status',
        'recalculated_amount', 'forfeited_to_tenant_amount', 'forfeited_to_saas_amount',
        'forfeited_to_seller_amount', 'gateway_reference', 'released_at', 'forfeited_at',
    ];

    // BR-25: one hold per party per sale_event — never pooled
    public function createHold(string $saleEventId, string $partyId, string $channel, float $amount): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId, 'party_id' => $partyId,
            'channel' => $channel, 'amount' => $amount, 'status' => 'held',
        ]);
        return $this->find($id);
    }

    public function findBySaleEventAndParty(string $saleEventId, string $partyId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)
            ->where('party_id', $partyId)
            ->orderBy('held_at', 'DESC')
            ->first();
    }

    public function findAllBySaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->where('status', 'held')->findAll();
    }

    public function setRecalculatedAmount(string $holdId, float $recalculatedAmount): array
    {
        $this->update($holdId, ['recalculated_amount' => $recalculatedAmount]);
        return $this->find($holdId);
    }

    public function markReleased(string $holdId): array
    {
        $this->update($holdId, ['status' => 'released', 'released_at' => date('Y-m-d H:i:s')]);
        return $this->find($holdId);
    }

    // BR-34: forfeiture allocation — split recorded explicitly
    public function markForfeited(string $holdId, float $tenantAmount, float $saasAmount, float $sellerAmount): array
    {
        $this->update($holdId, [
            'status' => 'forfeited',
            'forfeited_at' => date('Y-m-d H:i:s'),
            'forfeited_to_tenant_amount' => $tenantAmount,
            'forfeited_to_saas_amount' => $saasAmount,
            'forfeited_to_seller_amount' => $sellerAmount,
        ]);
        return $this->find($holdId);
    }
}
