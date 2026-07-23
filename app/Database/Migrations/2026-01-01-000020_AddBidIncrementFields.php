<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBidIncrementFields extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE sale_event ADD COLUMN bid_increment_amount NUMERIC(14,2);
            ALTER TABLE sale_event ADD COLUMN increment_halved_at TIMESTAMPTZ;
            ALTER TABLE sale_event ADD COLUMN anti_snipe_trigger_minutes INTEGER;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE sale_event DROP COLUMN IF EXISTS bid_increment_amount, DROP COLUMN IF EXISTS increment_halved_at, DROP COLUMN IF EXISTS anti_snipe_trigger_minutes;');
    }
}
