<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMediaTypeAndCompressionFields extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE listing_media_type AS ENUM ('photo', 'video');

            ALTER TABLE listing_media ADD COLUMN media_type listing_media_type NOT NULL DEFAULT 'photo';
            ALTER TABLE listing_media ADD COLUMN original_size_bytes BIGINT;
            ALTER TABLE listing_media ADD COLUMN compressed_size_bytes BIGINT;
            ALTER TABLE listing_media ADD COLUMN duration_seconds INTEGER;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing_media DROP COLUMN IF EXISTS media_type, DROP COLUMN IF EXISTS original_size_bytes, DROP COLUMN IF EXISTS compressed_size_bytes, DROP COLUMN IF EXISTS duration_seconds;');
        $this->db->query('DROP TYPE IF EXISTS listing_media_type;');
    }
}
