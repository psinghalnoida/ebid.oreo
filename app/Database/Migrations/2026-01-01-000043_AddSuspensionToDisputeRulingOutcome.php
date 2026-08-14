<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuspensionToDisputeRulingOutcome extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE dispute MODIFY COLUMN ruling_outcome ENUM(
            'force_log_noc', 'order_forfeiture', 'rating_consequence', 'dismissed', 'suspension'
        );");
    }

    public function down()
    {
    }
}
