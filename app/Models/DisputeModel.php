<?php

namespace App\Models;

use CodeIgniter\Model;

class DisputeModel extends Model
{
    protected $table            = 'dispute';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'sale_event_id', 'filed_by_party_id', 'respondent_party_id',
        'category', 'description', 'status', 'evidence_deadline_at',
        'ruling_authority_type', 'ruled_by_party_id', 'ruling_outcome',
        'ruling_rationale', 'ruled_at',
        'appealed_at', 'appeal_ruled_by_party_id', 'appeal_rationale', 'appeal_ruled_at',
    ];

    public function createDispute(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    // BR-40 9.1: pattern of repeated, baseless (dismissed) dispute-filing
    public function countDismissedFiledBy(string $partyId): int
    {
        return $this->where('filed_by_party_id', $partyId)
            ->where('ruling_outcome', 'dismissed')
            ->countAllResults();
    }
}
