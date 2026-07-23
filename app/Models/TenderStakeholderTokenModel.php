<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderStakeholderTokenModel extends Model
{
    protected $table            = 'tender_stakeholder_token';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'sale_event_id', 'token', 'label', 'created_by_party_id', 'revoked_at'];

    public function createToken(string $saleEventId, string $createdByPartyId, ?string $label = null): array
    {
        $id = \App\Libraries\Uuid::v4();
        $token = bin2hex(random_bytes(24));
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId, 'token' => $token,
            'label' => $label, 'created_by_party_id' => $createdByPartyId,
        ]);
        return $this->find($id);
    }

    public function findByToken(string $token): ?array
    {
        return $this->where('token', $token)->where('revoked_at', null)->first();
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->findAll();
    }

    public function revoke(string $tokenId): void
    {
        $this->update($tokenId, ['revoked_at' => date('Y-m-d H:i:s')]);
    }
}
