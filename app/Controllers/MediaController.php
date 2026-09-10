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
        // waiting on the background cron queue. Each of THIS batch's own
        // jobs is processed directly by id — not via
        // MediaQueueService::processNext(), which claims whatever job is
        // oldest platform-wide and would risk this request processing
        // (and reporting back) another seller's concurrent upload while
        // leaving this listing's own jobs stuck pending.
        $jobModel = new \App\Models\MediaUploadJobModel();
        $doneMedia = [];
        $failedFiles = [];
        foreach ($jobs as $job) {
            try {
                $media = $this->media->processJob($job);
                $jobModel->markDone($job['id'], $media['id']);
                $doneMedia[] = $media;
            } catch (\Throwable $e) {
                $jobModel->markFailed($job['id'], $e->getMessage());
                $failedFiles[] = ['original_filename' => $job['original_filename'], 'error' => $e->getMessage()];
            }
        }

        return $this->apiResponse([
            'media' => $doneMedia,
            'failed' => $failedFiles,
            'message' => count($doneMedia) . ' file(s) processed' . (count($failedFiles) > 0 ? ', ' . count($failedFiles) . ' failed' : '') . '.',
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
