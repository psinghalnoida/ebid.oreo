<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderEligibilityModel extends Model
{
    protected $table            = 'tender_eligibility';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'sale_event_id', 'party_id', 'source', 'approved_by_party_id'];

    public function grantEligibility(string $saleEventId, string $partyId, string $source, string $approvedByPartyId): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId, 'party_id' => $partyId,
            'source' => $source, 'approved_by_party_id' => $approvedByPartyId,
        ]);
        return $this->find($id);
    }

    public function isEligible(string $saleEventId, string $partyId): bool
    {
        return $this->where('sale_event_id', $saleEventId)->where('party_id', $partyId)->countAllResults() > 0;
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->orderBy('approved_at', 'ASC')->findAll();
    }
}
