<?php

namespace App\Models;

use CodeIgniter\Model;

class PayoutHoldModel extends Model
{
    protected $table            = 'payout_hold';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'settlement_id', 'party_id', 'bank_account_id', 'amount',
        'status', 'reviewed_by_party_id', 'review_notes', 'reviewed_at',
    ];

    public function createHold(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function findPendingForAccount(string $bankAccountId): ?array
    {
        return $this->where('bank_account_id', $bankAccountId)->where('status', 'pending')->first();
    }

    // A bank account is treated as "vetted" once ANY payout to it has been
    // explicitly released by an admin — subsequent payouts to the SAME
    // account don't require review again. See PayoutAccountService for
    // why this reading was chosen over a fixed re-review window (BR-50's
    // text doesn't specify one).
    public function hasEverBeenReleased(string $bankAccountId): bool
    {
        return $this->where('bank_account_id', $bankAccountId)->where('status', 'released')->countAllResults() > 0;
    }

    public function findPending(): array
    {
        return $this->where('status', 'pending')->orderBy('created_at', 'ASC')->findAll();
    }
}
