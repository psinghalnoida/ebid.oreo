<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRepresentativeMediaFlagToListing extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE listing ADD COLUMN media_is_representative_under_waiver BOOLEAN NOT NULL DEFAULT false;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing DROP COLUMN media_is_representative_under_waiver;');
    }
}
