<?php

namespace App\Models;

use CodeIgniter\Model;

class BuyerPreferenceModel extends Model
{
    protected $table            = 'buyer_preference';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;
    protected $allowedFields = [
        'id', 'party_id', 'preferred_categories', 'comfort_states', 'budget_min', 'budget_max', 'updated_at',
    ];

    public function findForParty(string $partyId): ?array
    {
        return $this->where('party_id', $partyId)->first();
    }

    public function upsert(string $partyId, array $data): array
    {
        $existing = $this->findForParty($partyId);
        $payload = array_merge($data, ['party_id' => $partyId, 'updated_at' => date('Y-m-d H:i:s')]);

        if ($existing) {
            $this->update($existing['id'], $payload);
            return $this->find($existing['id']);
        }

        $payload['id'] = \App\Libraries\Uuid::v4();
        $this->insert($payload);
        return $this->find($payload['id']);
    }
}
