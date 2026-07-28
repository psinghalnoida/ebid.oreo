<?php

namespace App\Controllers;

use App\Libraries\MediaService;
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

    private function requireLogin()
    {
        return session()->get('logged_in_party_id');
    }

    // PR-09: this only validates and STAGES files, then returns — the
    // actual compression/transcoding happens later, off the request
    // thread, via the background queue (MediaQueueService, drained by
    // `php spark process:media-queue` and the scheduler cron sweep).
    // That's what makes the upload genuinely non-blocking rather than
    // just fast-looking.
    public function upload(string $listingId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) {
            return redirect()->to('/login');
        }

        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return service('response')->setStatusCode(403)->setBody('Only the listing\'s seller may upload media to it.');
        }

        $photoFiles = $this->request->getFileMultiple('photos') ?: [];
        $videoFiles = $this->request->getFileMultiple('videos') ?: [];
        $documentFiles = $this->request->getFileMultiple('documents') ?: [];
        $files = array_merge($photoFiles, $videoFiles, $documentFiles);
        $gpsLat = $this->request->getPost('gps_lat') ?: null;
        $gpsLng = $this->request->getPost('gps_lng') ?: null;

        if (empty($files) || (count($files) === 1 && !$files[0]->isValid())) {
            return redirect()->to("/listings/{$listingId}")->with('error', 'No valid files were selected.');
        }

        try {
            $jobs = $this->media->enqueueUploads($listingId, $partyId, $files, $gpsLat ? (float) $gpsLat : null, $gpsLng ? (float) $gpsLng : null);
        } catch (\RuntimeException $e) {
            return redirect()->to("/listings/{$listingId}")->with('error', $e->getMessage());
        }

        return redirect()->to("/listings/{$listingId}")->with('error', count($jobs) . ' file(s) queued for processing — they\'ll appear below once the background queue finishes compressing them.');
    }

    public function setPrimary(string $listingId, string $mediaId)
    {
        $partyId = $this->requireLogin();
        if (!$partyId) {
            return redirect()->to('/login');
        }
        $listing = $this->listingModel->findActiveById($listingId);
        if (!$listing || $listing['seller_party_id'] !== $partyId) {
            return service('response')->setStatusCode(403)->setBody('Only the listing\'s seller may change the primary photo.');
        }
        $this->media->setPrimary($mediaId, $listingId);
        return redirect()->to("/listings/{$listingId}");
    }
}
