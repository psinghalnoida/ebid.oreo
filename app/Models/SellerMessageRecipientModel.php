<?php

namespace App\Models;

use CodeIgniter\Model;

// D-105: real per-recipient delivery record -- this IS the buyer's
// inbox. No SMS/email provider exists (D-104's own audit), so delivery
// here means "a row a buyer's real logged-in session can see," not a
// push notification -- the same honest scoping BR-46/BR-52 already
// apply to genuinely external-dependent features, except this one
// needs no external dependency at all to be real and working.
class SellerMessageRecipientModel extends Model
{
    protected $table            = 'seller_message_recipient';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'seller_message_id', 'buyer_party_id', 'delivered_at', 'read_at'];

    public function deliverTo(string $sellerMessageId, array $buyerPartyIds): void
    {
        foreach ($buyerPartyIds as $buyerId) {
            $this->insert([
                'id' => \App\Libraries\Uuid::v4(), 'seller_message_id' => $sellerMessageId, 'buyer_party_id' => $buyerId,
            ]);
        }
    }

    // Buyer's own inbox -- newest first, joined back to the message body
    // and which listing/seller it came from.
    public function findForBuyer(string $buyerPartyId): array
    {
        return $this->db->table('seller_message_recipient smr')
            ->select("smr.id as recipient_id, smr.delivered_at, smr.read_at,
                      sm.message_body, sm.listing_id, sm.seller_party_id,
                      l.category, l.subcategory")
            ->join('seller_message sm', 'sm.id = smr.seller_message_id')
            ->join('listing l', 'l.id = sm.listing_id')
            ->where('smr.buyer_party_id', $buyerPartyId)
            ->orderBy('smr.delivered_at', 'DESC')
            ->get()->getResultArray();
    }

    public function markRead(string $recipientId, string $buyerPartyId): bool
    {
        $row = $this->where('id', $recipientId)->where('buyer_party_id', $buyerPartyId)->first();
        if (!$row) {
            return false;
        }
        if (!$row['read_at']) {
            $this->update($recipientId, ['read_at' => date('Y-m-d H:i:s')]);
        }
        return true;
    }

    public function unreadCountForBuyer(string $buyerPartyId): int
    {
        return $this->where('buyer_party_id', $buyerPartyId)->where('read_at', null)->countAllResults();
    }
}
