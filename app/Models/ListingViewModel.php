<?php

namespace App\Models;

use CodeIgniter\Model;

// D-105: per-party view tracking for a listing -- powers the "Viewed
// Lot" yes/no flag on the seller-facing Lot Reach & Interest dashboard.
// Logged-in parties only; anonymous views still bump listing.view_count
// but have no party to attribute a row to here.
class ListingViewModel extends Model
{
    protected $table            = 'listing_view';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'listing_id', 'party_id', 'viewed_at'];

    // Idempotent: a buyer viewing the same listing 50 times still shows
    // as one "viewed" row, not 50 -- the aggregate count lives on
    // listing.view_count instead, which increments unconditionally.
    public function recordView(string $listingId, string $partyId): void
    {
        $existing = $this->where('listing_id', $listingId)->where('party_id', $partyId)->first();
        if ($existing) {
            $this->update($existing['id'], ['viewed_at' => date('Y-m-d H:i:s')]);
            return;
        }
        $this->insert([
            'id' => \App\Libraries\Uuid::v4(), 'listing_id' => $listingId, 'party_id' => $partyId,
        ]);
    }

    // Set of party_ids (as keys) who have viewed this listing -- used to
    // flag "Viewed Lot" against the matched-buyer list without an N+1
    // query per buyer.
    public function viewedPartyIdsForListing(string $listingId): array
    {
        $rows = $this->select('party_id')->where('listing_id', $listingId)->findAll();
        return array_flip(array_column($rows, 'party_id'));
    }
}
