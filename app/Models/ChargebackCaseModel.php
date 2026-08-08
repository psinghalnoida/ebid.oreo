<?php

namespace App\Models;

use CodeIgniter\Model;

class ChargebackCaseModel extends Model
{
    protected $table            = 'chargeback_case';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false; // filed_at is a DB default, never written by the app

    protected $allowedFields = [
        'id', 'emd_hold_id', 'sale_event_id', 'party_id', 'amount', 'filed_reason',
        'against_approved_forfeiture', 'evidence_package', 'status', 'evidence_assembled_at',
        'representment_outcome_by_party_id', 'representment_notes', 'representment_resolved_at',
        'integrity_reviewed_by_party_id', 'integrity_review_notes',
        'integrity_rating_consequence_applied', 'integrity_reviewed_at',
    ];

    // Postgres returns BOOLEAN columns as literal 't'/'f' strings via this
    // driver -- PHP treats the non-empty string "f" as truthy, so every
    // boolean column here must be cast explicitly on the way out, same
    // fix already established in ListingMediaModel::findForListing() for
    // is_primary.
    private function normalize(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $row['against_approved_forfeiture'] = in_array($row['against_approved_forfeiture'], [true, 't', 1, '1'], true);
        if ($row['integrity_rating_consequence_applied'] !== null) {
            $row['integrity_rating_consequence_applied'] = in_array($row['integrity_rating_consequence_applied'], [true, 't', 1, '1'], true);
        }
        return $row;
    }

    private function normalizeAll(array $rows): array
    {
        return array_map(fn ($r) => $this->normalize($r), $rows);
    }

    public function find($id = null)
    {
        return $this->normalize(parent::find($id));
    }

    public function createCase(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert($data + ['id' => $id]);
        return $this->find($id);
    }

    // Open on the representment track: still awaiting the gateway's
    // eventual decision, which a SaaS Admin records manually (PR-30's
    // step 192 requires real gateway submission this app doesn't have).
    public function findOpenRepresentment(): array
    {
        return $this->normalizeAll($this->db->table('chargeback_case cc')
            ->select('cc.*, p.mobile_number, se.sale_format')
            ->join('party p', 'p.id = cc.party_id')
            ->join('sale_event se', 'se.id = cc.sale_event_id')
            ->where('cc.status', 'represented')
            ->orderBy('cc.filed_at', 'ASC')
            ->get()->getResultArray());
    }

    // Open on the integrity track: filed against an already-approved
    // forfeiture and not yet reviewed by a SaaS Admin (BR-52, PR-30 step
    // 193) -- deliberately independent of the representment status above.
    public function findPendingIntegrityReview(): array
    {
        return $this->normalizeAll($this->db->table('chargeback_case cc')
            ->select('cc.*, p.mobile_number, se.sale_format')
            ->join('party p', 'p.id = cc.party_id')
            ->join('sale_event se', 'se.id = cc.sale_event_id')
            ->where('cc.against_approved_forfeiture', true)
            ->where('cc.integrity_reviewed_at IS NULL')
            ->orderBy('cc.filed_at', 'ASC')
            ->get()->getResultArray());
    }

    public function findResolved(int $limit = 50): array
    {
        return $this->normalizeAll($this->db->table('chargeback_case cc')
            ->select('cc.*, p.mobile_number')
            ->join('party p', 'p.id = cc.party_id')
            ->whereIn('cc.status', ['resolved_won', 'resolved_lost'])
            ->orderBy('cc.representment_resolved_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray());
    }

    public function markRepresentmentOutcome(string $caseId, string $adminId, string $status, string $notes): array
    {
        $this->update($caseId, [
            'status' => $status,
            'representment_outcome_by_party_id' => $adminId,
            'representment_notes' => $notes,
            'representment_resolved_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($caseId);
    }

    public function markIntegrityReviewed(string $caseId, string $adminId, string $notes, bool $ratingConsequenceApplied): array
    {
        $this->update($caseId, [
            'integrity_reviewed_by_party_id' => $adminId,
            'integrity_review_notes' => $notes,
            'integrity_rating_consequence_applied' => $ratingConsequenceApplied,
            'integrity_reviewed_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($caseId);
    }
}
