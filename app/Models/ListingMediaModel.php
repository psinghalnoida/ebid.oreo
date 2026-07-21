<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingMediaModel extends Model
{
    protected $table            = 'listing_media';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'listing_id', 'uploaded_by_party_id', 'file_path', 'original_filename',
        'is_primary', 'gps_lat', 'gps_lng', 'captured_at',
    ];

    public function createMedia(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    public function findForListing(string $listingId): array
    {
        $rows = $this->where('listing_id', $listingId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
        // Postgres returns booleans as literal 't'/'f' strings via this
        // driver — PHP treats the non-empty string "f" as truthy, so this
        // must be cast explicitly or every is_primary check silently
        // evaluates true regardless of the actual value.
        foreach ($rows as &$row) {
            $row['is_primary'] = in_array($row['is_primary'], [true, 't', 1, '1'], true);
        }
        return $rows;
    }

    public function countForListing(string $listingId): int
    {
        return $this->where('listing_id', $listingId)->countAllResults();
    }

    public function clearPrimaryForListing(string $listingId): void
    {
        $this->where('listing_id', $listingId)->set('is_primary', false)->update();
    }

    public function setPrimary(string $mediaId, string $listingId): void
    {
        $this->clearPrimaryForListing($listingId);
        $this->update($mediaId, ['is_primary' => true]);
    }
}
