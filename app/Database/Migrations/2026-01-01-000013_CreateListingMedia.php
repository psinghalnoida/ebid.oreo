<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateListingMedia extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE listing_media (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                listing_id          UUID NOT NULL REFERENCES listing(id),
                uploaded_by_party_id UUID NOT NULL REFERENCES party(id),
                file_path            TEXT NOT NULL,
                original_filename    TEXT,
                is_primary           BOOLEAN NOT NULL DEFAULT false,

                -- BR-45: GPS + timestamp captured at moment of upload where
                -- the browser/device supports it. Best-effort on the web —
                -- see docs/DECISIONS.md for the honest limitation versus a
                -- native app's automatic EXIF capture.
                gps_lat               NUMERIC(10,7),
                gps_lng               NUMERIC(10,7),
                captured_at            TIMESTAMPTZ,

                created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_listing_media_listing ON listing_media (listing_id);

            -- BR-11: exactly one primary photo per listing
            CREATE UNIQUE INDEX uq_one_primary_per_listing
                ON listing_media (listing_id)
                WHERE is_primary = true;
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS listing_media CASCADE;');
    }
}
