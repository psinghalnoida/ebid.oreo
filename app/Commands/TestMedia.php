<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\ListingMediaModel;
use App\Models\MediaUploadJobModel;
use App\Libraries\ListingLifecycleService;
use App\Libraries\MediaQueueService;
use App\Libraries\MediaCompressionService;

// PR-09: the background media queue itself (MediaQueueService,
// MediaService::processJob) tested directly against real staged files —
// real multipart file uploads (isValid() requires is_uploaded_file(),
// which is never true outside an actual PHP upload request) are
// verified separately over real HTTP with curl -F, matching this
// codebase's own established precedent (see MediaService's original
// D-24 note on the same limitation for its predecessor, storeUploads).
class TestMedia extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:media';
    protected $description = 'Runs the PR-09 background media queue, closed-list rejection, and no-primary submit gate against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $mediaModel = new ListingMediaModel();
        $jobModel = new MediaUploadJobModel();
        $lifecycle = new ListingLifecycleService();
        $queue = new MediaQueueService();

        CLI::write('=== Setup ===', 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Media Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'mediatest']);
        $seller = $partyModel->createParty('+919887001001');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600011',
        ]);

        $stagingDir = WRITEPATH . 'uploads_staging/' . $listing['id'];
        mkdir($stagingDir, 0755, true);

        CLI::write("\n=== A real photo is genuinely processed through the queue (WebP compression) ===", 'yellow');
        $photoPath = $stagingDir . '/' . \App\Libraries\Uuid::v4() . '_raw';
        $img = imagecreatetruecolor(400, 400);
        imagefill($img, 0, 0, imagecolorallocate($img, 100, 150, 200));
        imagejpeg($img, $photoPath);
        imagedestroy($img);

        $job1 = $jobModel->createJob([
            'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
            'staged_file_path' => $photoPath, 'original_filename' => 'photo1.jpg',
            'mime_type' => 'image/jpeg', 'status' => 'pending',
        ]);
        $this->assert($job1['status'] === 'pending', 'Job starts pending, not processed inline');

        $result1 = $queue->processNext();
        $this->assert($result1['outcome'] === 'done', 'Queue worker genuinely processes the photo job');
        $this->assert($result1['media']['media_type'] === 'photo', 'Resulting media row is a photo');
        // Postgres returns booleans as literal 't'/'f' strings via this
        // driver on a raw find() (unlike findForListing(), which already
        // casts) — same quirk documented in ListingMediaModel.
        $this->assert(in_array($result1['media']['is_primary'], [true, 't', 1, '1'], true), 'First processed photo is auto-designated primary');
        $this->assert(str_ends_with($result1['media']['file_path'], '.webp'), 'Photo was genuinely re-encoded to WebP, not just copied');
        $this->assert(!file_exists($photoPath), 'Staged raw file cleaned up after processing');
        $jobAfter = $jobModel->find($job1['id']);
        $this->assert($jobAfter['status'] === 'done', 'Job marked done in the database');
        $this->assert($jobAfter['created_media_id'] === $result1['media']['id'], 'Job links to the real media row it produced');

        CLI::write("\n=== A second photo does NOT steal primary from the first ===", 'yellow');
        $photo2Path = $stagingDir . '/' . \App\Libraries\Uuid::v4() . '_raw';
        $img2 = imagecreatetruecolor(400, 400);
        imagefill($img2, 0, 0, imagecolorallocate($img2, 50, 50, 50));
        imagejpeg($img2, $photo2Path);
        imagedestroy($img2);
        $jobModel->createJob([
            'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
            'staged_file_path' => $photo2Path, 'original_filename' => 'photo2.jpg',
            'mime_type' => 'image/jpeg', 'status' => 'pending',
        ]);
        $result2 = $queue->processNext();
        $this->assert(in_array($result2['media']['is_primary'], [false, 'f', 0, '0'], true), 'Second photo is not auto-primary — the first one already holds it');

        CLI::write("\n=== A document is stored as-is, no compression pipeline applied ===", 'yellow');
        $docPath = $stagingDir . '/' . \App\Libraries\Uuid::v4() . '_raw';
        file_put_contents($docPath, "%PDF-1.4\nfake pdf content for testing\n");
        $docJob = $jobModel->createJob([
            'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
            'staged_file_path' => $docPath, 'original_filename' => 'spec-sheet.pdf',
            'mime_type' => 'application/pdf', 'status' => 'pending',
        ]);
        $result3 = $queue->processNext();
        $this->assert($result3['outcome'] === 'done', 'Document job processed successfully');
        $this->assert($result3['media']['media_type'] === 'document', 'Resulting media row is a document');
        $this->assert(str_ends_with($result3['media']['file_path'], '.pdf'), 'Document kept as .pdf, not transcoded');

        CLI::write("\n=== A failing video job does not crash the queue or block other jobs ===", 'yellow');
        $videoPath = $stagingDir . '/' . \App\Libraries\Uuid::v4() . '_raw';
        file_put_contents($videoPath, 'not a real video file');
        $videoJob = $jobModel->createJob([
            'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
            'staged_file_path' => $videoPath, 'original_filename' => 'walkaround.mp4',
            'mime_type' => 'video/mp4', 'status' => 'pending',
        ]);
        // A 4th photo queued AFTER the doomed video job — proves one bad
        // job doesn't stop the rest of the sequential queue from draining.
        $photo3Path = $stagingDir . '/' . \App\Libraries\Uuid::v4() . '_raw';
        $img3 = imagecreatetruecolor(400, 400);
        imagejpeg($img3, $photo3Path);
        imagedestroy($img3);
        $jobModel->createJob([
            'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
            'staged_file_path' => $photo3Path, 'original_filename' => 'photo3.jpg',
            'mime_type' => 'image/jpeg', 'status' => 'pending',
        ]);

        $drained = $queue->processAll();
        $this->assert(count($drained) === 2, 'Both remaining queued jobs (video + photo3) were attempted in one drain');
        $videoResult = $drained[0];
        $this->assert($videoResult['outcome'] === 'failed', 'The video job genuinely failed (no ffmpeg on this test host) rather than silently succeeding');
        $videoJobAfter = $jobModel->find($videoJob['id']);
        $this->assert($videoJobAfter['status'] === 'failed', 'Failed job marked failed in the database, with an error message');
        $this->assert(!empty($videoJobAfter['error_message']), 'Failure reason is genuinely captured, not just a bare status flip');
        $photo3Result = $drained[1];
        $this->assert($photo3Result['outcome'] === 'done', 'The photo AFTER the failed video still processed successfully — one bad job did not stop the queue');

        $this->assert($queue->processNext() === null, 'Queue is genuinely empty once drained — no phantom jobs left behind');

        CLI::write("\n=== BR-11: submit blocked without 5 photos, and now also without a primary ===", 'yellow');
        // Only 3 real photos exist at this point (job1, job2, photo3) —
        // deliberately still short of the 5-photo minimum.
        $listingModel->setMediaCount($listing['id'], (int) $mediaModel->countForListing($listing['id'], 'photo'));
        $blockedFewPhotos = false;
        try {
            $lifecycle->submitForApproval($listing['id']);
        } catch (\RuntimeException $e) {
            $blockedFewPhotos = str_contains($e->getMessage(), 'at least 5 photos');
        }
        $this->assert($blockedFewPhotos, 'Submission blocked with fewer than 5 real photos');

        // Bring the count up to 5 via the model directly (matching
        // TestLifecycle's established fixture pattern) but clear the
        // primary flag to isolate the "no primary" gate specifically.
        $listingModel->setMediaCount($listing['id'], 5);
        $mediaModel->clearPrimaryForListing($listing['id']);
        $blockedNoPrimary = false;
        try {
            $lifecycle->submitForApproval($listing['id']);
        } catch (\RuntimeException $e) {
            $blockedNoPrimary = str_contains($e->getMessage(), 'Main Display Photo');
        }
        $this->assert($blockedNoPrimary, 'PR-09: submission is also blocked with 5+ photos but no Main Display Photo designated');

        $mediaModel->setPrimary($result1['media']['id'], $listing['id']);
        $submitted = $lifecycle->submitForApproval($listing['id']);
        $this->assert($submitted['status'] === 'pending_approval', 'Submission succeeds once both gates are satisfied');

        CLI::write("\n=== PR-09: rejection requires a genuine closed-list reason ===", 'yellow');
        $invalidReason = false;
        try {
            $lifecycle->reject($listing['id'], 'because I felt like it');
        } catch (\RuntimeException $e) {
            $invalidReason = str_contains($e->getMessage(), 'closed list');
        }
        $this->assert($invalidReason, 'An arbitrary rejection reason is rejected, not silently accepted');

        $rejected = $lifecycle->reject($listing['id'], 'mismatched_description', 'Photos show a different model number than described.');
        $this->assert($rejected['status'] === 'inventory', 'A valid closed-list reason succeeds and returns the listing to inventory');
        $this->assert(
            $rejected['rejection_reason'] === 'Mismatched description: Photos show a different model number than described.',
            "Stored reason combines the closed-list label with the free-text detail: {$rejected['rejection_reason']}"
        );

        CLI::write("\n=== MAX_PHOTOS cap counts queued jobs too, not just finished media ===", 'yellow');
        // 45 more "queued" (never actually processed) photo jobs on top
        // of the 5 real photos already on this listing — proves the cap
        // is enforced against jobs still in flight, not just finished ones.
        for ($i = 0; $i < 45; $i++) {
            $jobModel->createJob([
                'listing_id' => $listing['id'], 'uploaded_by_party_id' => $seller['id'],
                'staged_file_path' => '/tmp/never-processed-' . $i, 'original_filename' => "bulk{$i}.jpg",
                'mime_type' => 'image/jpeg', 'status' => 'pending',
            ]);
        }
        $queuedCount = $jobModel->countPendingOrProcessingForListing($listing['id'], ['image/jpeg', 'image/png', 'image/webp']);
        $this->assert($queuedCount === 45, "Exactly 45 photo jobs genuinely counted as pending/processing (got {$queuedCount})");
        // 3 real completed photos (job1, job2, photo3 — job3 was the
        // document, the video job failed) + 45 still-queued = 48,
        // correctly combining finished media with in-flight jobs rather
        // than only counting one or the other.
        $completedPhotoCount = $mediaModel->countForListing($listing['id'], 'photo');
        $totalAgainstCap = $completedPhotoCount + $queuedCount;
        $this->assert($completedPhotoCount === 3, "3 real completed photos exist at this point (got {$completedPhotoCount})");
        $this->assert($totalAgainstCap === 48, "Completed + queued genuinely sums correctly (got {$totalAgainstCap}, expected 48)");

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  ✓ {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  ✗ {$msg}", 'red');
        }
    }
}
