<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderInterestModel extends Model
{
    protected $table            = 'tender_interest';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'sale_event_id', 'party_id'];

    public function registerInterest(string $saleEventId, string $partyId): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert(['id' => $id, 'sale_event_id' => $saleEventId, 'party_id' => $partyId]);
        return $this->find($id);
    }

    public function hasRegisteredInterest(string $saleEventId, string $partyId): bool
    {
        return $this->where('sale_event_id', $saleEventId)->where('party_id', $partyId)->countAllResults() > 0;
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->orderBy('registered_at', 'ASC')->findAll();
    }
}
