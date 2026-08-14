<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateListingMedia extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE listing_media (

                id                  CHAR(36) PRIMARY KEY,

                listing_id CHAR(36) NOT NULL,

                uploaded_by_party_id CHAR(36) NOT NULL,

                file_path            TEXT NOT NULL,

                original_filename    TEXT,

                is_primary           BOOLEAN NOT NULL DEFAULT false,


                -- BR-45: GPS + timestamp captured at moment of upload where
                -- the browser/device supports it. Best-effort on the web —
                -- see docs/DECISIONS.md for the honest limitation versus a
                -- native app's automatic EXIF capture.
                gps_lat               NUMERIC(10,7),

                gps_lng               NUMERIC(10,7),

                captured_at            DATETIME(6),


                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),


                -- BR-11: exactly one primary photo per listing. MySQL has no
                -- partial/filtered unique index -- see CreateSaleEvent.php's
                -- open_sale_marker for the same generated-marker-column
                -- technique used to replicate the original Postgres
                -- partial-unique-index semantics exactly.
                primary_marker CHAR(1) GENERATED ALWAYS AS (
                    CASE WHEN is_primary = true THEN 'Y' END
                ) STORED,

                UNIQUE KEY uq_one_primary_per_listing (listing_id, primary_marker),

                CONSTRAINT fk_listing_media_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id),

                CONSTRAINT fk_listing_media_uploaded_by_party_id FOREIGN KEY (uploaded_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_listing_media_listing ON listing_media (listing_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS listing_media CASCADE;');
    }
}
