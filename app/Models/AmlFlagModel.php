<?php

namespace App\Models;

use CodeIgniter\Model;

class AmlFlagModel extends Model
{
    protected $table            = 'aml_flag';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'pattern_type', 'party_id', 'related_emd_hold_id', 'external_reference',
        'detail', 'status', 'reviewed_by_party_id', 'review_notes', 'str_filed',
        'str_reference', 'reviewed_at',
    ];

    public function createFlag(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function existsForHold(string $emdHoldId, string $patternType): bool
    {
        return $this->where('related_emd_hold_id', $emdHoldId)
            ->where('pattern_type', $patternType)
            ->countAllResults() > 0;
    }

    public function existsForPartyAndReference(string $partyId, string $externalReference): bool
    {
        return $this->where('party_id', $partyId)
            ->where('external_reference', $externalReference)
            ->where('pattern_type', 'shared_external_reference')
            ->countAllResults() > 0;
    }

    public function findOpen(): array
    {
        return $this->where('status', 'open')->orderBy('created_at', 'ASC')->findAll();
    }

    public function findReviewed(int $limit = 50): array
    {
        return $this->where('status !=', 'open')->orderBy('reviewed_at', 'DESC')->limit($limit)->findAll();
    }
}
