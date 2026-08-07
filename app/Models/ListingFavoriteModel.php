<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingFavoriteModel extends Model
{
    protected $table            = 'listing_favorite';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'party_id', 'listing_id'];

    public function isFavorited(string $partyId, string $listingId): bool
    {
        return $this->where('party_id', $partyId)->where('listing_id', $listingId)->countAllResults() > 0;
    }

    public function add(string $partyId, string $listingId): void
    {
        if ($this->isFavorited($partyId, $listingId)) {
            return;
        }
        $this->insert(['id' => \App\Libraries\Uuid::v4(), 'party_id' => $partyId, 'listing_id' => $listingId]);
    }

    public function remove(string $partyId, string $listingId): void
    {
        $this->where('party_id', $partyId)->where('listing_id', $listingId)->delete();
    }

    // D-105: the reverse of findForParty() below -- which buyers have
    // favorited THIS listing, for the seller-facing Lot Reach & Interest
    // dashboard.
    public function favoritedPartyIdsForListing(string $listingId): array
    {
        $rows = $this->select('party_id')->where('listing_id', $listingId)->findAll();
        return array_flip(array_column($rows, 'party_id'));
    }

    public function findForParty(string $partyId): array
    {
        return $this->db->table('listing_favorite lf')
            ->select('lf.id as favorite_id, lf.created_at as favorited_at, l.id as listing_id, l.category, l.subcategory,
                      l.physical_condition, se.id as sale_event_id, se.sale_format, se.status as sale_status,
                      COALESCE(se.current_price, se.reserve_value, se.expected_value) as display_price,
                      lm.file_path as photo_path')
            ->join('listing l', 'l.id = lf.listing_id')
            ->join('sale_event se', 'se.listing_id = l.id', 'left')
            ->join('listing_media lm', 'lm.listing_id = l.id AND lm.is_primary = true', 'left', false)
            ->where('lf.party_id', $partyId)
            ->orderBy('lf.created_at', 'DESC')
            ->get()->getResultArray();
    }
}
