<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddRelatedGroupToListing extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing ADD COLUMN related_group_id CHAR(36);
            ALTER TABLE listing ADD COLUMN related_group_label TEXT;

            CREATE INDEX idx_listing_related_group ON listing (related_group_id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing DROP COLUMN related_group_id;
            ALTER TABLE listing DROP COLUMN related_group_label;
        SQL);
    }
}
