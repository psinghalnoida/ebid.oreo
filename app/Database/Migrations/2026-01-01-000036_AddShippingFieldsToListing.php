<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddShippingFieldsToListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing ADD COLUMN shipping_enabled BOOLEAN NOT NULL DEFAULT false;
            ALTER TABLE listing ADD COLUMN shipping_cost_type TEXT;
            ALTER TABLE listing ADD COLUMN shipping_fixed_cost NUMERIC(10,2);
            ALTER TABLE listing ADD COLUMN shipping_variable_rate_per_km NUMERIC(10,2);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing DROP COLUMN IF EXISTS shipping_enabled;
            ALTER TABLE listing DROP COLUMN IF EXISTS shipping_cost_type;
            ALTER TABLE listing DROP COLUMN IF EXISTS shipping_fixed_cost;
            ALTER TABLE listing DROP COLUMN IF EXISTS shipping_variable_rate_per_km;
        SQL);
    }
}
