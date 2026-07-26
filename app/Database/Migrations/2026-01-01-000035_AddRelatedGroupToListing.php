<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRelatedGroupToListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing ADD COLUMN related_group_id UUID;
            ALTER TABLE listing ADD COLUMN related_group_label TEXT;

            CREATE INDEX idx_listing_related_group ON listing (related_group_id);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing DROP COLUMN IF EXISTS related_group_id;
            ALTER TABLE listing DROP COLUMN IF EXISTS related_group_label;
        SQL);
    }
}
