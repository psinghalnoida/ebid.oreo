<?php

namespace App\Models;

use CodeIgniter\Model;

class SearchHistoryModel extends Model
{
    protected $table            = 'search_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'filters'];

    public function record(string $partyId, array $filters): void
    {
        $this->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $partyId, 'filters' => json_encode($filters)]);
    }

    public function findRecentForParty(string $partyId, int $limit = 20): array
    {
        return $this->where('party_id', $partyId)->orderBy('created_at', 'DESC')->findAll($limit);
    }
}
