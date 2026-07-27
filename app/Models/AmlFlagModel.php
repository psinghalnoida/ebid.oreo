<?php

namespace App\Models;

use CodeIgniter\Model;

class AmlFlagModel extends Model
{
    protected $table            = 'aml_flag';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false; // created_at is a DB default, never written by the app

    protected $allowedFields = [
        'id', 'flag_type', 'party_id', 'detail', 'status',
        'reviewed_by_party_id', 'review_notes', 'str_reference', 'reviewed_at',
    ];

    public function createFlag(string $flagType, string $partyId, array $detail): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'flag_type' => $flagType, 'party_id' => $partyId,
            'detail' => json_encode($detail, JSON_UNESCAPED_SLASHES),
        ]);
        return $this->find($id);
    }

    // Idempotency guard, same pattern as StandingReviewService::hasOpenCase
    // — don't raise a second flag of the same type while one is still open.
    public function hasOpenFlag(string $partyId, string $flagType): bool
    {
        return $this->where('party_id', $partyId)
            ->where('flag_type', $flagType)
            ->where('status', 'open')
            ->countAllResults() > 0;
    }

    public function findOpen(): array
    {
        return $this->db->table('aml_flag af')
            ->select('af.*, p.mobile_number')
            ->join('party p', 'p.id = af.party_id')
            ->where('af.status', 'open')
            ->orderBy('af.created_at', 'ASC')
            ->get()->getResultArray();
    }

    public function findReviewed(int $limit = 50): array
    {
        return $this->db->table('aml_flag af')
            ->select('af.*, p.mobile_number, r.mobile_number as reviewer_mobile')
            ->join('party p', 'p.id = af.party_id')
            ->join('party r', 'r.id = af.reviewed_by_party_id', 'left')
            ->where('af.status !=', 'open')
            ->orderBy('af.reviewed_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    public function markReviewed(string $flagId, string $reviewerPartyId, string $status, ?string $strReference, string $notes): array
    {
        $this->update($flagId, [
            'status' => $status,
            'reviewed_by_party_id' => $reviewerPartyId,
            'review_notes' => $notes,
            'str_reference' => $strReference,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($flagId);
    }
}
