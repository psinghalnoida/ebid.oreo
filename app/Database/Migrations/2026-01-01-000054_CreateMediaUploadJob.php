<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
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
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE media_upload_job (

                id                    CHAR(36) PRIMARY KEY,

                listing_id CHAR(36) NOT NULL,

                uploaded_by_party_id CHAR(36) NOT NULL,

                staged_file_path      TEXT NOT NULL,

                original_filename     TEXT,

                mime_type             TEXT NOT NULL,

                gps_lat               NUMERIC(10,7),

                gps_lng               NUMERIC(10,7),

                status                ENUM('pending', 'processing', 'done', 'failed') NOT NULL DEFAULT 'pending',

                error_message         TEXT,

                created_media_id CHAR(36),

                created_at            DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                processed_at          DATETIME(6),

                CONSTRAINT fk_media_upload_job_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id),

                CONSTRAINT fk_media_upload_job_uploaded_by_party_id FOREIGN KEY (uploaded_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_media_upload_job_created_media_id FOREIGN KEY (created_media_id) REFERENCES listing_media(id)
            );

            -- Sequential FIFO drain: oldest pending job first.
            CREATE INDEX idx_media_job_status_created ON media_upload_job (status, created_at);
            CREATE INDEX idx_media_job_listing ON media_upload_job (listing_id);
        SQL);

        // PR-09: "optional video and documents" — documents were never a
        // supported media_type at all. Run as its own statement, matching
        // this project's established pattern (D-15/AddSuperAdminRole) for
        // ALTER TYPE ... ADD VALUE.
        $this->db->query("ALTER TABLE listing_media MODIFY COLUMN media_type ENUM('photo', 'video', 'document') NOT NULL DEFAULT 'photo';");
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            DROP TABLE IF EXISTS media_upload_job CASCADE;
        SQL);
        // Postgres cannot drop a single enum value — the 'document'
        // addition to listing_media_type is not reversed here.
    }
}
