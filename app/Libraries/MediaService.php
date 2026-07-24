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
    private const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4', 'video/quicktime', 'video/webm'];
    private const MAX_FILE_SIZE_KB = 8192; // 8MB per photo, pre-compression
    private const MAX_VIDEO_SIZE_KB = 512000; // 500MB per video, pre-transcode — a raw phone video can be large before compression brings it down

    private ListingMediaModel $mediaModel;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->mediaModel = new ListingMediaModel();
        $this->listingModel = new ListingModel();
    }

    // Validates and stores a batch of uploaded files — every file is
    // genuinely compressed (WebP re-encode for photos, ffmpeg transcode
    // for video) before being written to its final location. Does NOT
    // enforce the minimum-5 requirement here (a seller may upload in
    // multiple batches) — that's enforced at submission time in
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

        $validFiles = array_filter($files, fn($f) => $f->isValid() && !$f->hasMoved());

        // BR-11's 5-50 count applies to PHOTOS specifically — video is
        // optional and separate, never counted against this cap.
        $existingPhotoCount = $this->mediaModel->countForListing($listingId, 'photo');
        $newPhotoCount = count(array_filter($validFiles, fn($f) => in_array($f->getMimeType(), self::ALLOWED_IMAGE_MIME_TYPES, true)));
        if ($existingPhotoCount + $newPhotoCount > self::MAX_PHOTOS) {
            throw new \RuntimeException(
                "BR-11 violation: maximum " . self::MAX_PHOTOS . " photos per listing (already have {$existingPhotoCount}, tried to add {$newPhotoCount})"
            );
        }

        $uploadDir = WRITEPATH . '../public/uploads/listings/' . $listingId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $compression = new MediaCompressionService();
        $stored = [];
        foreach ($validFiles as $file) {
            $mimeType = $file->getMimeType();
            $isImage = in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true);
            $isVideo = in_array($mimeType, self::ALLOWED_VIDEO_MIME_TYPES, true);

            if (!$isImage && !$isVideo) {
                throw new \RuntimeException("Unsupported file type: {$mimeType} (JPEG/PNG/WebP for photos, MP4/MOV/WebM for video)");
            }

            $sizeCapKb = $isVideo ? self::MAX_VIDEO_SIZE_KB : self::MAX_FILE_SIZE_KB;
            if ($file->getSize() / 1024 > $sizeCapKb) {
                throw new \RuntimeException("File too large: {$file->getName()} (max {$sizeCapKb}KB before compression)");
            }

            // Move the raw upload to a temp path first — compression
            // reads from here and writes the real, final file separately;
            // the original raw upload is discarded once compression
            // succeeds, never kept alongside the compressed version.
            $tempName = Uuid::v4() . '_raw';
            $tempPath = $uploadDir . '/' . $tempName;
            $file->move($uploadDir, $tempName);

            $mediaData = [
                'listing_id' => $listingId,
                'uploaded_by_party_id' => $uploaderPartyId,
                'original_filename' => $file->getClientName(),
                'is_primary' => ($isImage && $existingPhotoCount === 0 && empty(array_filter($stored, fn($s) => $s['media_type'] === 'photo'))),
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'captured_at' => ($gpsLat !== null) ? date('Y-m-d H:i:s') : null,
            ];

            if ($isImage) {
                $finalName = Uuid::v4() . '.webp';
                $finalPath = $uploadDir . '/' . $finalName;
                $result = $compression->compressImage($tempPath, $finalPath, $mimeType);
                unlink($tempPath); // raw upload discarded — only the compressed version is kept

                $mediaData['media_type'] = 'photo';
                $mediaData['file_path'] = "uploads/listings/{$listingId}/{$finalName}";
                $mediaData['original_size_bytes'] = $result['originalSizeBytes'];
                $mediaData['compressed_size_bytes'] = $result['compressedSizeBytes'];
            } else {
                $finalName = Uuid::v4() . '.mp4';
                $finalPath = $uploadDir . '/' . $finalName;
                $result = $compression->transcodeVideo($tempPath, $finalPath);
                unlink($tempPath);

                $mediaData['media_type'] = 'video';
                $mediaData['file_path'] = "uploads/listings/{$listingId}/{$finalName}";
                $mediaData['original_size_bytes'] = $result['originalSizeBytes'];
                $mediaData['compressed_size_bytes'] = $result['compressedSizeBytes'];
                $mediaData['duration_seconds'] = $result['durationSeconds'];
            }

            $media = $this->mediaModel->createMedia($mediaData);
            $stored[] = $media;
        }

        // media_count feeds directly into BR-11's "5 photos required"
        // check (ListingLifecycleService::submitForApproval) — must be
        // the PHOTO count specifically, not photos+video combined, or a
        // seller could pass that gate with fewer real photos than required.
        $newPhotoCountTotal = $this->mediaModel->countForListing($listingId, 'photo');
        $this->listingModel->setMediaCount($listingId, $newPhotoCountTotal);

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
