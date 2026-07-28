<?php

namespace App\Libraries;

use App\Models\MediaUploadJobModel;

// PR-09: the actual "background worker queue, processed sequentially"
// — claims and processes one media_upload_job at a time, in FIFO order,
// platform-wide (not parallel, not per-listing). One failing file (e.g.
// a corrupt upload, or video transcoding when ffmpeg isn't installed on
// this server) is caught and marked failed without stopping the rest of
// the queue from draining.
class MediaQueueService
{
    private MediaUploadJobModel $jobModel;
    private MediaService $media;

    public function __construct()
    {
        $this->jobModel = new MediaUploadJobModel();
        $this->media = new MediaService();
    }

    // Processes exactly one job, if any is pending. Returns the outcome
    // ('done'/'failed') and the job, or null if the queue is empty.
    public function processNext(): ?array
    {
        $job = $this->jobModel->claimNextPending();
        if (!$job) {
            return null;
        }

        try {
            $mediaRow = $this->media->processJob($job);
            $this->jobModel->markDone($job['id'], $mediaRow['id']);
            return ['outcome' => 'done', 'job' => $job, 'media' => $mediaRow];
        } catch (\Throwable $e) {
            $this->jobModel->markFailed($job['id'], $e->getMessage());
            return ['outcome' => 'failed', 'job' => $job, 'error' => $e->getMessage()];
        }
    }

    // Drains the entire queue, sequentially, up to $maxJobs in one call
    // (a safety cap, not a real limit — the scheduler sweep calls this
    // repeatedly on its own cadence).
    public function processAll(int $maxJobs = 200): array
    {
        $results = [];
        for ($i = 0; $i < $maxJobs; $i++) {
            $result = $this->processNext();
            if ($result === null) {
                break;
            }
            $results[] = $result;
        }
        return $results;
    }
}
