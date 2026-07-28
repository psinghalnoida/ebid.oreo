<?php

namespace App\Models;

use CodeIgniter\Model;

class PayoutReleaseReviewModel extends Model
{
    protected $table            = 'payout_release_review';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false; // created_at is a DB default, never written by the app

    protected $allowedFields = [
        'id', 'emd_hold_id', 'party_id', 'amount', 'release_type',
        'tenant_amount', 'saas_amount', 'buyer_refund',
        'status', 'reviewed_by_party_id', 'decision_rationale', 'reviewed_at',
    ];

    public function createReview(string $holdId, string $partyId, float $amount, string $releaseType, ?float $tenantAmount = null, ?float $saasAmount = null, ?float $buyerRefund = null): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'emd_hold_id' => $holdId, 'party_id' => $partyId, 'amount' => $amount,
            'release_type' => $releaseType, 'tenant_amount' => $tenantAmount,
            'saas_amount' => $saasAmount, 'buyer_refund' => $buyerRefund,
        ]);
        return $this->find($id);
    }

    // Idempotency guard, same pattern as AmlFlagModel::hasOpenFlag —
    // don't create a second pending review for a hold that's already
    // awaiting one.
    public function hasPendingReviewForHold(string $holdId): bool
    {
        return $this->where('emd_hold_id', $holdId)->where('status', 'pending')->countAllResults() > 0;
    }

    // Super Admin sees every pending review, platform-wide.
    public function findPending(): array
    {
        return $this->db->table('payout_release_review prr')
            ->select('prr.*, p.mobile_number')
            ->join('party p', 'p.id = prr.party_id')
            ->where('prr.status', 'pending')
            ->orderBy('prr.created_at', 'ASC')
            ->get()->getResultArray();
    }

    public function findReviewed(int $limit = 50): array
    {
        return $this->db->table('payout_release_review prr')
            ->select('prr.*, p.mobile_number, r.mobile_number as reviewer_mobile')
            ->join('party p', 'p.id = prr.party_id')
            ->join('party r', 'r.id = prr.reviewed_by_party_id', 'left')
            ->where('prr.status !=', 'pending')
            ->orderBy('prr.reviewed_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    // BR-50: a Tenant Admin sees only reviews whose underlying sale event
    // belongs to a tenant they actually administer.
    public function findPendingForTenants(array $tenantIds): array
    {
        if (empty($tenantIds)) {
            return [];
        }
        return $this->db->table('payout_release_review prr')
            ->select('prr.*, p.mobile_number')
            ->join('party p', 'p.id = prr.party_id')
            ->join('emd_hold eh', 'eh.id = prr.emd_hold_id')
            ->join('sale_event se', 'se.id = eh.sale_event_id')
            ->where('prr.status', 'pending')
            ->whereIn('se.tenant_id', $tenantIds)
            ->orderBy('prr.created_at', 'ASC')
            ->get()->getResultArray();
    }

    public function findReviewedForTenants(array $tenantIds, int $limit = 50): array
    {
        if (empty($tenantIds)) {
            return [];
        }
        return $this->db->table('payout_release_review prr')
            ->select('prr.*, p.mobile_number, r.mobile_number as reviewer_mobile')
            ->join('party p', 'p.id = prr.party_id')
            ->join('party r', 'r.id = prr.reviewed_by_party_id', 'left')
            ->join('emd_hold eh', 'eh.id = prr.emd_hold_id')
            ->join('sale_event se', 'se.id = eh.sale_event_id')
            ->where('prr.status !=', 'pending')
            ->whereIn('se.tenant_id', $tenantIds)
            ->orderBy('prr.reviewed_at', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();
    }

    public function markDecision(string $reviewId, string $reviewerPartyId, string $status, string $rationale): array
    {
        $this->update($reviewId, [
            'status' => $status, 'reviewed_by_party_id' => $reviewerPartyId,
            'decision_rationale' => $rationale, 'reviewed_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($reviewId);
    }
}
