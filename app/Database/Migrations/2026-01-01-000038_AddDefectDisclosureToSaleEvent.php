<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddDefectDisclosureToSaleEvent extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_known_damage TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_missing_components TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_nonfunctional_aspects TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_completed_at DATETIME(6);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE sale_event DROP COLUMN defect_disclosure_known_damage;
            ALTER TABLE sale_event DROP COLUMN defect_disclosure_missing_components;
            ALTER TABLE sale_event DROP COLUMN defect_disclosure_nonfunctional_aspects;
            ALTER TABLE sale_event DROP COLUMN defect_disclosure_completed_at;
        SQL);
    }
}
