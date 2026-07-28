<?php

namespace App\Models;

use CodeIgniter\Model;

class MediaUploadJobModel extends Model
{
    protected $table            = 'media_upload_job';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'listing_id', 'uploaded_by_party_id', 'staged_file_path', 'original_filename',
        'mime_type', 'gps_lat', 'gps_lng', 'status', 'error_message', 'created_media_id', 'processed_at',
    ];

    public function createJob(array $data): array
    {
        $id = \App\Libraries\Uuid::v4();
        $data['id'] = $id;
        $this->insert($data);
        return $this->find($id);
    }

    // Sequential FIFO: the single oldest pending job across the whole
    // platform, not scoped to one listing — PR-09 says the queue is
    // processed sequentially, not per-listing in parallel.
    public function claimNextPending(): ?array
    {
        $job = $this->where('status', 'pending')->orderBy('created_at', 'ASC')->first();
        if (!$job) {
            return null;
        }
        $this->update($job['id'], ['status' => 'processing']);
        return $this->find($job['id']);
    }

    public function markDone(string $jobId, string $mediaId): void
    {
        $this->update($jobId, ['status' => 'done', 'created_media_id' => $mediaId, 'processed_at' => date('Y-m-d H:i:s')]);
    }

    public function markFailed(string $jobId, string $errorMessage): void
    {
        $this->update($jobId, ['status' => 'failed', 'error_message' => $errorMessage, 'processed_at' => date('Y-m-d H:i:s')]);
    }

    // Used to pre-emptively enforce BR-11's 50-photo cap against
    // still-queued jobs, not just already-processed media — otherwise a
    // seller could race past the cap by uploading faster than the queue
    // drains.
    public function countPendingOrProcessingForListing(string $listingId, array $mimeTypes): int
    {
        return $this->where('listing_id', $listingId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereIn('mime_type', $mimeTypes)
            ->countAllResults();
    }

    public function findForListing(string $listingId): array
    {
        return $this->where('listing_id', $listingId)
            ->whereIn('status', ['pending', 'processing', 'failed'])
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }
}
