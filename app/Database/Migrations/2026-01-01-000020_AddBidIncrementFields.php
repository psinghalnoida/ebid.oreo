<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddBidIncrementFields extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE sale_event ADD COLUMN bid_increment_amount NUMERIC(14,2);
            ALTER TABLE sale_event ADD COLUMN increment_halved_at DATETIME(6);
            ALTER TABLE sale_event ADD COLUMN anti_snipe_trigger_minutes INTEGER;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE sale_event DROP COLUMN bid_increment_amount, DROP COLUMN increment_halved_at, DROP COLUMN anti_snipe_trigger_minutes;');
    }
}
