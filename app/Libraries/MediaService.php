<?php

namespace App\Libraries;

use App\Models\ListingMediaModel;
use App\Models\ListingModel;
use App\Models\MediaUploadJobModel;

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
//
// PR-09: uploads are queued, not processed inline within the HTTP
// request — enqueueUploads() only validates and stages the raw files
// (fast); the actual WebP/ffmpeg compression happens later, off the
// request thread, in processJob() (called by MediaQueueService's
// worker). This is what makes "the interface never freezes while the
// seller continues filling in details" genuinely true rather than just
// asserted.
class MediaService
{
    private const MIN_PHOTOS = 5;
    private const MAX_PHOTOS = 50;
    private const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4', 'video/quicktime', 'video/webm'];
    private const ALLOWED_DOCUMENT_MIME_TYPES = ['application/pdf'];
    private const MAX_FILE_SIZE_KB = 8192; // 8MB per photo, pre-compression
    private const MAX_VIDEO_SIZE_KB = 512000; // 500MB per video, pre-transcode — a raw phone video can be large before compression brings it down
    private const MAX_DOCUMENT_SIZE_KB = 20480; // 20MB per document

    private ListingMediaModel $mediaModel;
    private ListingModel $listingModel;
    private MediaUploadJobModel $jobModel;

    public function __construct()
    {
        $this->mediaModel = new ListingMediaModel();
        $this->listingModel = new ListingModel();
        $this->jobModel = new MediaUploadJobModel();
    }

    // Validates a batch of uploaded files and stages them for background
    // processing — returns immediately once files are moved to the
    // staging directory and job rows are created, without waiting for
    // any compression/transcoding to happen.
    public function enqueueUploads(string $listingId, string $uploaderPartyId, array $files, ?float $gpsLat, ?float $gpsLng): array
    {
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing) {
            throw new \RuntimeException('Listing not found');
        }
        if (!in_array($listing['status'], ['inventory', 'pending_approval'], true)) {
            throw new \RuntimeException('Media can only be added while a listing is in inventory or pending_approval');
        }

        $validFiles = array_filter($files, fn($f) => $f->isValid() && !$f->hasMoved());

        // BR-11's 5-50 count applies to PHOTOS specifically — video and
        // documents are optional and separate, never counted against
        // this cap. Counts both already-processed photos AND
        // still-queued photo jobs, so a seller can't race past the cap
        // by uploading faster than the queue drains.
        $existingPhotoCount = $this->mediaModel->countForListing($listingId, 'photo')
            + $this->jobModel->countPendingOrProcessingForListing($listingId, self::ALLOWED_IMAGE_MIME_TYPES);
        $newPhotoCount = count(array_filter($validFiles, fn($f) => in_array($f->getMimeType(), self::ALLOWED_IMAGE_MIME_TYPES, true)));
        if ($existingPhotoCount + $newPhotoCount > self::MAX_PHOTOS) {
            throw new \RuntimeException(
                "BR-11 violation: maximum " . self::MAX_PHOTOS . " photos per listing (already have/queued {$existingPhotoCount}, tried to add {$newPhotoCount})"
            );
        }

        // Staged OUTSIDE the public webroot — these are raw, unprocessed
        // uploads (not yet compressed/scanned), never meant to be
        // directly served until processJob() produces the real,
        // compressed file under public/uploads/listings/.
        $stagingDir = WRITEPATH . 'uploads_staging/' . $listingId;
        if (!is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $jobs = [];
        foreach ($validFiles as $file) {
            $mimeType = $file->getMimeType();
            $isImage = in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true);
            $isVideo = in_array($mimeType, self::ALLOWED_VIDEO_MIME_TYPES, true);
            $isDocument = in_array($mimeType, self::ALLOWED_DOCUMENT_MIME_TYPES, true);

            if (!$isImage && !$isVideo && !$isDocument) {
                throw new \RuntimeException("Unsupported file type: {$mimeType} (JPEG/PNG/WebP for photos, MP4/MOV/WebM for video, PDF for documents)");
            }

            $sizeCapKb = $isVideo ? self::MAX_VIDEO_SIZE_KB : ($isDocument ? self::MAX_DOCUMENT_SIZE_KB : self::MAX_FILE_SIZE_KB);
            if ($file->getSize() / 1024 > $sizeCapKb) {
                throw new \RuntimeException("File too large: {$file->getName()} (max {$sizeCapKb}KB before compression)");
            }

            $stagedName = Uuid::v4() . '_raw';
            $stagedPath = $stagingDir . '/' . $stagedName;
            $file->move($stagingDir, $stagedName);

            $job = $this->jobModel->createJob([
                'listing_id' => $listingId,
                'uploaded_by_party_id' => $uploaderPartyId,
                'staged_file_path' => $stagedPath,
                'original_filename' => $file->getClientName(),
                'mime_type' => $mimeType,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
                'status' => 'pending',
            ]);
            $jobs[] = $job;
        }

        return $jobs;
    }

    // Processes exactly one staged job into a real listing_media row —
    // called by MediaQueueService's sequential worker, never directly
    // from an HTTP request. Throws on failure; the caller is responsible
    // for catching and marking the job failed (so one bad file doesn't
    // stop the rest of the queue from draining).
    public function processJob(array $job): array
    {
        $listingId = $job['listing_id'];
        $mimeType = $job['mime_type'];
        $stagedPath = $job['staged_file_path'];

        if (!file_exists($stagedPath)) {
            throw new \RuntimeException('Staged upload file is missing — cannot process');
        }

        $isImage = in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true);
        $isVideo = in_array($mimeType, self::ALLOWED_VIDEO_MIME_TYPES, true);

        $uploadDir = WRITEPATH . '../public/uploads/listings/' . $listingId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $mediaData = [
            'listing_id' => $listingId,
            'uploaded_by_party_id' => $job['uploaded_by_party_id'],
            'original_filename' => $job['original_filename'],
            'gps_lat' => $job['gps_lat'],
            'gps_lng' => $job['gps_lng'],
            'captured_at' => ($job['gps_lat'] !== null) ? date('Y-m-d H:i:s') : null,
        ];

        $compression = new MediaCompressionService();

        if ($isImage) {
            // First photo to finish processing becomes primary by
            // default — checked at PROCESS time (not enqueue time),
            // since jobs may finish out of upload order.
            $isFirstPhoto = $this->mediaModel->countForListing($listingId, 'photo') === 0;

            $finalName = Uuid::v4() . '.webp';
            $finalPath = $uploadDir . '/' . $finalName;
            $result = $compression->compressImage($stagedPath, $finalPath, $mimeType);
            unlink($stagedPath);

            $mediaData['media_type'] = 'photo';
            $mediaData['is_primary'] = $isFirstPhoto;
            $mediaData['file_path'] = "uploads/listings/{$listingId}/{$finalName}";
            $mediaData['original_size_bytes'] = $result['originalSizeBytes'];
            $mediaData['compressed_size_bytes'] = $result['compressedSizeBytes'];
        } elseif ($isVideo) {
            $finalName = Uuid::v4() . '.mp4';
            $finalPath = $uploadDir . '/' . $finalName;
            $result = $compression->transcodeVideo($stagedPath, $finalPath);
            unlink($stagedPath);

            $mediaData['media_type'] = 'video';
            $mediaData['is_primary'] = false;
            $mediaData['file_path'] = "uploads/listings/{$listingId}/{$finalName}";
            $mediaData['original_size_bytes'] = $result['originalSizeBytes'];
            $mediaData['compressed_size_bytes'] = $result['compressedSizeBytes'];
            $mediaData['duration_seconds'] = $result['durationSeconds'];
        } else {
            // Documents are stored as-is — no compression pipeline
            // applies to a PDF the way it does to images/video.
            $finalName = Uuid::v4() . '.pdf';
            $finalPath = $uploadDir . '/' . $finalName;
            $originalSize = filesize($stagedPath);
            rename($stagedPath, $finalPath);

            $mediaData['media_type'] = 'document';
            $mediaData['is_primary'] = false;
            $mediaData['file_path'] = "uploads/listings/{$listingId}/{$finalName}";
            $mediaData['original_size_bytes'] = $originalSize;
            $mediaData['compressed_size_bytes'] = $originalSize;
        }

        $media = $this->mediaModel->createMedia($mediaData);

        // media_count feeds directly into BR-11's "5 photos required"
        // check (ListingLifecycleService::submitForApproval) — must be
        // the PHOTO count specifically, not photos+video+documents
        // combined, or a seller could pass that gate with fewer real
        // photos than required.
        $newPhotoCountTotal = $this->mediaModel->countForListing($listingId, 'photo');
        $this->listingModel->setMediaCount($listingId, $newPhotoCountTotal);

        return $media;
    }

    public function setPrimary(string $mediaId, string $listingId): void
    {
        $this->mediaModel->setPrimary($mediaId, $listingId);
    }

    public function getMediaForListing(string $listingId): array
    {
        return $this->mediaModel->findForListing($listingId);
    }

    public function getQueuedJobsForListing(string $listingId): array
    {
        return $this->jobModel->findForListing($listingId);
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
