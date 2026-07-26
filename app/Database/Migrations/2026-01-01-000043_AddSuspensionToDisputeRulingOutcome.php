<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuspensionToDisputeRulingOutcome extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TYPE dispute_ruling_outcome ADD VALUE 'suspension';");
    }

    public function down()
    {
    }
}
