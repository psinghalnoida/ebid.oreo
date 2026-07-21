<?php

namespace App\Libraries;

use App\Models\ListingMediaModel;
use App\Models\ListingModel;

// BR-11: 5-50 photos per listing, exactly one primary.
// BR-45: GPS + timestamp captured "at the moment of capture" — on a
// native mobile app this is automatic EXIF/sensor data. This is a WEB
// application, so true automatic capture isn't achievable the same way;
// this service accepts GPS/timestamp as optional client-supplied fields
// (populated via the browser's Geolocation API where the user grants
// permission) rather than guaranteeing it the way a native app could.
// Flagged explicitly, not silently treated as equivalent — see D-24.
//
// BR-59's "genuine photo of the actual item, not stock photography"
// requirement is NOT enforced here or anywhere in this codebase — that
// would require computer-vision fraud detection, out of scope. This is a
// trust/audit-time concern, not a code-enforced one.
class MediaService
{
    private const MIN_PHOTOS = 5;
    private const MAX_PHOTOS = 50;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_FILE_SIZE_KB = 8192; // 8MB per photo

    private ListingMediaModel $mediaModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->mediaModel = new ListingMediaModel();
        $this->listingModel = new ListingModel();
    }

    // Validates and stores a batch of uploaded files. Does NOT enforce
    // the minimum-5 requirement here (a seller may upload in multiple
    // batches) — that's enforced at submission time in
    // ListingLifecycleService::submitForApproval instead. This method
    // only enforces the maximum and per-file constraints.
    public function storeUploads(string $listingId, string $uploaderPartyId, array $files, ?float $gpsLat, ?float $gpsLng): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }
        if (!in_array($listing['status'], ['inventory', 'pending_approval'], true)) {
            throw new \RuntimeException('Media can only be added while a listing is in inventory or pending_approval');
        }

        $existingCount = $this->mediaModel->countForListing($listingId);
        $validFiles = array_filter($files, fn($f) => $f->isValid() && !$f->hasMoved());

        if ($existingCount + count($validFiles) > self::MAX_PHOTOS) {
            throw new \RuntimeException(
                "BR-11 violation: maximum " . self::MAX_PHOTOS . " photos per listing (already have {$existingCount}, tried to add " . count($validFiles) . ")"
            );
        }

        $uploadDir = WRITEPATH . '../public/uploads/listings/' . $listingId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $stored = [];
        foreach ($validFiles as $file) {
            if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES, true)) {
                throw new \RuntimeException("Unsupported file type: {$file->getMimeType()} (only JPEG/PNG/WebP allowed)");
            }
            if ($file->getSize() / 1024 > self::MAX_FILE_SIZE_KB) {
                throw new \RuntimeException("File too large: {$file->getName()} (max " . self::MAX_FILE_SIZE_KB . "KB)");
            }

            $newName = Uuid::v4() . '.' . $file->getExtension();
            $file->move($uploadDir, $newName);

            $isFirstPhoto = ($existingCount === 0 && empty($stored));
            $media = $this->mediaModel->createMedia([
                'listing_id' => $listingId,
                'uploaded_by_party_id' => $uploaderPartyId,
                'file_path' => "uploads/listings/{$listingId}/{$newName}",
                'original_filename' => $file->getClientName(),
                'is_primary' => $isFirstPhoto,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'captured_at' => ($gpsLat !== null) ? date('Y-m-d H:i:s') : null,
            ]);
            $stored[] = $media;
        }

        $newCount = $this->mediaModel->countForListing($listingId);
        $this->listingModel->setMediaCount($listingId, $newCount);

        return $stored;
    }

    public function setPrimary(string $mediaId, string $listingId): void
    {
        $this->mediaModel->setPrimary($mediaId, $listingId);
    }

    public function getMediaForListing(string $listingId): array
    {
        return $this->mediaModel->findForListing($listingId);
    }

    public static function minPhotos(): int
    {
        return self::MIN_PHOTOS;
    }

    public static function maxPhotos(): int
    {
        return self::MAX_PHOTOS;
    }
}
