<?php

namespace App\Models;

use CodeIgniter\Model;

class ListingModel extends Model
{
    protected $table            = 'listing';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'tenant_id', 'seller_party_id', 'physical_condition', 'category', 'subcategory',
        'quantity', 'quantity_basis', 'make_model', 'yard_location_address',
        'yard_location_pin', 'inspector_party_id', 'inspector_contact_note',
        'status', 'rejection_reason', 'superseded_by_listing_id', 'archived_at', 'updated_at',
        'media_tier', 'media_count',
    ];

    public function setMediaCount(string $listingId, int $count): void
    {
        $this->update($listingId, ['media_count' => $count, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function findActiveById(string $id): ?array
    {
        return $this->where('id', $id)->where('archived_at', null)->first();
    }

    public function findByTenant(string $tenantId, ?string $status = null): array
    {
        $builder = $this->where('tenant_id', $tenantId)->where('archived_at', null);
        if ($status !== null) {
            $builder = $builder->where('status', $status);
        }
        return $builder->orderBy('created_at', 'DESC')->findAll();
    }

    // BR-11: universal required metadata at creation
    public function createListing(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $data['status'] = 'inventory';
        $this->insert($data);
        return $this->find($id);
    }

    // BR-13: only status transitions are allowed directly on an existing
    // listing; every other field is immutable once ACTIVE.
    public function transitionStatus(string $listingId, string $newStatus, ?string $rejectionReason = null): array
    {
        $this->update($listingId, [
            'status' => $newStatus,
            'rejection_reason' => $rejectionReason,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->find($listingId);
    }

    // BR-13: archive-and-recreate — the original is archived and points to
    // its replacement; a fresh listing re-enters the lifecycle.
    public function supersede(string $originalListingId, array $newListingData): array
    {
        $newListing = $this->createListing($newListingData);
        $this->update($originalListingId, [
            'archived_at' => date('Y-m-d H:i:s'),
            'superseded_by_listing_id' => $newListing['id'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['archivedOriginal' => $this->find($originalListingId), 'newListing' => $newListing];
    }
}
