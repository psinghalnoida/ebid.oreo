<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDefectDisclosureToSaleEvent extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_known_damage TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_missing_components TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_nonfunctional_aspects TEXT;
            ALTER TABLE sale_event ADD COLUMN defect_disclosure_completed_at TIMESTAMPTZ;
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE sale_event DROP COLUMN IF EXISTS defect_disclosure_known_damage;
            ALTER TABLE sale_event DROP COLUMN IF EXISTS defect_disclosure_missing_components;
            ALTER TABLE sale_event DROP COLUMN IF EXISTS defect_disclosure_nonfunctional_aspects;
            ALTER TABLE sale_event DROP COLUMN IF EXISTS defect_disclosure_completed_at;
        SQL);
    }
}
