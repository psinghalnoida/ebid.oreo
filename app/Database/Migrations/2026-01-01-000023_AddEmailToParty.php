<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailToParty extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party ADD COLUMN recovery_email TEXT;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE party DROP COLUMN IF EXISTS recovery_email;');
    }
}
