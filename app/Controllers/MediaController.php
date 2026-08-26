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

    // PR-09: this only validates and STAGES files, then returns — the
    // actual compression/transcoding happens later, off the request
    // thread, via the background queue (MediaQueueService, drained by
    // `php spark process:media-queue` and the scheduler cron sweep).
    // That's what makes the upload genuinely non-blocking rather than
    // just fast-looking. Uploaded as multipart/form-data (not JSON) —
    // React sends this via FormData, same as any other file upload.
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

        return $this->response->setStatusCode(202)->setJSON([
            'jobs' => $jobs,
            'message' => count($jobs) . ' file(s) queued for processing — they\'ll appear once the background queue finishes compressing them.',
        ]);
    }

    public function setPrimary(string $listingId, string $mediaId)
    {
        $partyId = UserAuthContext::partyId();
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return $this->jsonError(403, 'forbidden', 'Only the listing\'s seller may change the primary photo.');
        }
        $this->media->setPrimary($mediaId, $listingId);
        return $this->response->setJSON(['media' => (new \App\Models\ListingMediaModel())->findForListing($listingId)]);
    }
}
