<?php

namespace App\Models;

use CodeIgniter\Model;

class SavedSearchModel extends Model
{
    protected $table            = 'saved_search';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'label', 'filters'];

    public function create(string $partyId, string $label, array $filters): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'party_id' => $partyId, 'label' => $label, 'filters' => json_encode($filters)]);
        return $this->find($id);
    }

    public function findForParty(string $partyId): array
    {
        return $this->where('party_id', $partyId)->orderBy('created_at', 'DESC')->findAll();
    }
}
