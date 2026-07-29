<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyAddressModel extends Model
{
    protected $table            = 'party_address';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'party_id', 'address_type', 'line1', 'line2', 'city', 'district',
        'state', 'country', 'pin_code', 'gps_lat', 'gps_lng', 'updated_at',
    ];

    public function forParty(string $partyId): array
    {
        return $this->where('party_id', $partyId)->orderBy('address_type', 'ASC')->findAll();
    }

    // BR-18: one row per (party, address_type) — an existing row is
    // replaced in place, not duplicated.
    public function upsert(string $partyId, string $addressType, array $data): array
    {
        $existing = $this->where('party_id', $partyId)->where('address_type', $addressType)->first();
        $data['party_id'] = $partyId;
        $data['address_type'] = $addressType;
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            $this->update($existing['id'], $data);
            return $this->find($existing['id']);
        }

        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }
}
