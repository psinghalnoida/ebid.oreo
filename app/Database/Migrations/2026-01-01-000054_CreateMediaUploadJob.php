<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// PR-09: "Bulk uploads are placed into a background worker queue,
// processed sequentially, so the interface never freezes while the
// seller continues filling in details." Previously every upload was
// compressed/transcoded synchronously inside the HTTP request — this
// table backs a genuine, DB-persisted job queue instead, drained by
// `php spark process:media-queue` (also wired into the existing
// SchedulerService cron sweep), matching this codebase's established
// "stage now, scheduler finalizes later" pattern (BR-50 payout
// cooling-off, account-deletion grace period).
class CreateMediaUploadJob extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE media_job_status AS ENUM ('pending', 'processing', 'done', 'failed');

            CREATE TABLE media_upload_job (
                id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                listing_id            UUID NOT NULL REFERENCES listing(id),
                uploaded_by_party_id  UUID NOT NULL REFERENCES party(id),
                staged_file_path      TEXT NOT NULL,
                original_filename     TEXT,
                mime_type             TEXT NOT NULL,
                gps_lat               NUMERIC(10,7),
                gps_lng               NUMERIC(10,7),
                status                media_job_status NOT NULL DEFAULT 'pending',
                error_message         TEXT,
                created_media_id      UUID REFERENCES listing_media(id),
                created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                processed_at          TIMESTAMPTZ
            );

            -- Sequential FIFO drain: oldest pending job first.
            CREATE INDEX idx_media_job_status_created ON media_upload_job (status, created_at);
            CREATE INDEX idx_media_job_listing ON media_upload_job (listing_id);
        SQL);

        // PR-09: "optional video and documents" — documents were never a
        // supported media_type at all. Run as its own statement, matching
        // this project's established pattern (D-15/AddSuperAdminRole) for
        // ALTER TYPE ... ADD VALUE.
        $this->db->query("ALTER TYPE listing_media_type ADD VALUE IF NOT EXISTS 'document';");
    }

    public function down()
    {
        $this->db->query(<<<SQL
            DROP TABLE IF EXISTS media_upload_job CASCADE;
            DROP TYPE IF EXISTS media_job_status;
        SQL);
        // Postgres cannot drop a single enum value — the 'document'
        // addition to listing_media_type is not reversed here.
    }
}
