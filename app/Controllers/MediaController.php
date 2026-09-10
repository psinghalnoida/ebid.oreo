<?php

namespace App\Controllers;

use App\Libraries\MediaService;
use App\Libraries\UserAuthContext;
use App\Models\ListingModel;

class MediaController extends BaseController
{
    private MediaService $media;
    private ListingModel $listingModel;

    public function __construct()
    {
        $this->media = new MediaService();
        $this->listingModel = new ListingModel();
    }

    // Files are validated, staged, AND fully processed (compressed/
    // transcoded into real listing_media rows) synchronously within this
    // one request — no background cron dependency. This trades a faster
    // response for not needing `php spark process:media-queue` / the
    // scheduler cron sweep to be running on the server for uploads to
    // ever finish. Uploaded as multipart/form-data (not JSON) — React
    // sends this via FormData, same as any other file upload.
    public function upload(string $listingId)
    {
        $partyId = UserAuthContext::partyId();

        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return $this->jsonError(403, 'forbidden', 'Only the listing\'s seller may upload media to it.');
        }

        $photoFiles = $this->request->getFileMultiple('photos') ?: [];
        $videoFiles = $this->request->getFileMultiple('videos') ?: [];
        $documentFiles = $this->request->getFileMultiple('documents') ?: [];
        $files = array_merge($photoFiles, $videoFiles, $documentFiles);
        $gpsLat = $this->request->getPost('gps_lat') ?: null;
        $gpsLng = $this->request->getPost('gps_lng') ?: null;

        if (empty($files) || (count($files) === 1 && !$files[0]->isValid())) {
            return $this->jsonError(422, 'no_files', 'No valid files were selected.');
        }

        try {
            $jobs = $this->media->enqueueUploads($listingId, $partyId, $files, $gpsLat ? (float) $gpsLat : null, $gpsLng ? (float) $gpsLng : null);
        } catch (\RuntimeException $e) {
            return $this->jsonError(422, 'upload_failed', $e->getMessage());
        }

        // Processed synchronously, right here in the request, instead of
        // waiting on the background cron queue (MediaQueueService is
        // still what does the actual compression/transcoding — this just
        // drains this batch's jobs immediately rather than leaving them
        // pending for a scheduled sweep to pick up later).
        $queue = new \App\Libraries\MediaQueueService();
        $results = [];
        foreach ($jobs as $job) {
            $results[] = $queue->processNext();
        }

        $done = array_filter($results, fn($r) => $r && $r['outcome'] === 'done');
        $failed = array_filter($results, fn($r) => $r && $r['outcome'] === 'failed');

        return $this->apiResponse([
            'media' => array_values(array_map(fn($r) => $r['media'], $done)),
            'failed' => array_values(array_map(fn($r) => ['original_filename' => $r['job']['original_filename'], 'error' => $r['error']], $failed)),
            'message' => count($done) . ' file(s) processed' . (count($failed) > 0 ? ', ' . count($failed) . ' failed' : '') . '.',
        ], null, 200);
    }

    public function setPrimary(string $listingId, string $mediaId)
    {
        $partyId = UserAuthContext::partyId();
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return $this->jsonError(403, 'forbidden', 'Only the listing\'s seller may change the primary photo.');
        }
        $this->media->setPrimary($mediaId, $listingId);
        return $this->apiResponse(['media' => (new \App\Models\ListingMediaModel())->findForListing($listingId)]);
    }
}
