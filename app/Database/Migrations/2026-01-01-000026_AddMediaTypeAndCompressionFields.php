<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddMediaTypeAndCompressionFields extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing_media ADD COLUMN media_type ENUM('photo', 'video') NOT NULL DEFAULT 'photo';
            ALTER TABLE listing_media ADD COLUMN original_size_bytes BIGINT;
            ALTER TABLE listing_media ADD COLUMN compressed_size_bytes BIGINT;
            ALTER TABLE listing_media ADD COLUMN duration_seconds INTEGER;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing_media DROP COLUMN media_type, DROP COLUMN original_size_bytes, DROP COLUMN compressed_size_bytes, DROP COLUMN duration_seconds;');
    }
}
