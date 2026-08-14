<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddMediaTierToListing extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing ADD COLUMN media_tier ENUM('verified', 'certified_by_seller') NOT NULL DEFAULT 'certified_by_seller';
            ALTER TABLE listing ADD COLUMN media_count INTEGER NOT NULL DEFAULT 0;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing DROP COLUMN media_tier, DROP COLUMN media_count;');
    }
}
