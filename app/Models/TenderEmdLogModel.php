<?php

namespace App\Models;

use CodeIgniter\Model;

class TenderEmdLogModel extends Model
{
    protected $table            = 'tender_emd_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'sale_event_id', 'party_id', 'amount', 'payment_location_note', 'no_emd_reason', 'logged_by_party_id'];

    public function logEmd(string $saleEventId, string $partyId, ?float $amount, ?string $locationNote, ?string $noEmdReason, string $loggedByPartyId): array
    {
        if ($amount !== null && $amount > 0) {
            if (!$locationNote) {
                throw new \RuntimeException('A payment location/method note is required when logging a real EMD amount.');
            }
            $noEmdReason = null;
        } else {
            if (!$noEmdReason) {
                throw new \RuntimeException('A reason is required when logging that no EMD was collected.');
            }
            $amount = 0.0; // matches the NOT NULL DEFAULT 0 column — never pass null
            $locationNote = null;
        }

        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'sale_event_id' => $saleEventId, 'party_id' => $partyId,
            'amount' => $amount, 'payment_location_note' => $locationNote,
            'no_emd_reason' => $noEmdReason, 'logged_by_party_id' => $loggedByPartyId,
        ]);
        return $this->find($id);
    }

    public function findForSaleEvent(string $saleEventId): array
    {
        return $this->where('sale_event_id', $saleEventId)->orderBy('logged_at', 'ASC')->findAll();
    }

    public function findForParty(string $saleEventId, string $partyId): ?array
    {
        return $this->where('sale_event_id', $saleEventId)->where('party_id', $partyId)->orderBy('logged_at', 'DESC')->first();
    }
}
