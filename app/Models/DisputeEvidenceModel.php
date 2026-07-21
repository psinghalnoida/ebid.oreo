<?php

namespace App\Models;

use CodeIgniter\Model;

class DisputeEvidenceModel extends Model
{
    protected $table            = 'dispute_evidence';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'dispute_id', 'submitted_by_party_id', 'content'];

    public function createEvidence(string $disputeId, string $partyId, string $content): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'dispute_id' => $disputeId, 'submitted_by_party_id' => $partyId, 'content' => $content]);
        return $this->find($id);
    }

    public function findForDispute(string $disputeId): array
    {
        return $this->where('dispute_id', $disputeId)->orderBy('created_at', 'ASC')->findAll();
    }
}
