<?php

namespace App\Models;

use CodeIgniter\Model;

// D-105: one row per bulk-message send from a Market Maker (seller) to
// every buyer matched against one of their live listings.
class SellerMessageModel extends Model
{
    protected $table            = 'seller_message';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'listing_id', 'seller_party_id', 'message_body', 'matched_buyer_count', 'created_at'];

    public function createMessage(string $listingId, string $sellerPartyId, string $body, int $matchedCount): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'listing_id' => $listingId, 'seller_party_id' => $sellerPartyId,
            'message_body' => $body, 'matched_buyer_count' => $matchedCount,
        ]);
        return $this->find($id);
    }
}
