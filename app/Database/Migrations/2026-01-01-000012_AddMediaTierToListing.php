<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMediaTierToListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE listing_media_tier AS ENUM ('verified', 'certified_by_seller');

            ALTER TABLE listing ADD COLUMN media_tier listing_media_tier NOT NULL DEFAULT 'certified_by_seller';
            ALTER TABLE listing ADD COLUMN media_count INTEGER NOT NULL DEFAULT 0;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing DROP COLUMN IF EXISTS media_tier, DROP COLUMN IF EXISTS media_count;');
        $this->db->query('DROP TYPE IF EXISTS listing_media_tier;');
    }
}
