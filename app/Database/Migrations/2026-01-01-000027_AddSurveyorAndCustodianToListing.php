<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSurveyorAndCustodianToListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing ADD COLUMN surveyor_party_id UUID REFERENCES party(id);
            ALTER TABLE listing ADD COLUMN custodian_party_id UUID REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing DROP COLUMN IF EXISTS surveyor_party_id, DROP COLUMN IF EXISTS custodian_party_id;');
    }
}
