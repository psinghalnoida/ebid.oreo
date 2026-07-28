<?php

namespace App\Models;

use CodeIgniter\Model;

class PartyBankAccountModel extends Model
{
    protected $table            = 'party_bank_account';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'party_id', 'account_holder_name', 'account_number', 'ifsc_code',
        'status', 'activates_at', 'initiated_by_party_id',
    ];

    public function createAccount(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    // The party's current bank account — whichever row is most recent,
    // regardless of whether it's still cooling off or already active.
    // Only one row is ever non-superseded at a time (see supersedeCurrent).
    public function findCurrentForParty(string $partyId): ?array
    {
        return $this->where('party_id', $partyId)
            ->where('status !=', 'superseded')
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    public function supersedeCurrent(string $partyId): void
    {
        $this->where('party_id', $partyId)->where('status !=', 'superseded')->set('status', 'superseded')->update();
    }

    public function activate(string $accountId): void
    {
        $this->update($accountId, ['status' => 'active']);
    }

    // Cooling-off rows whose 24h window has genuinely lapsed — candidates
    // for the scheduler to promote to 'active'.
    public function findDueForActivation(): array
    {
        return $this->where('status', 'cooling_off')
            ->where('activates_at <', date('Y-m-d H:i:s'))
            ->findAll();
    }
}
