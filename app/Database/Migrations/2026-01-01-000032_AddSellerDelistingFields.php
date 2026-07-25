<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSellerDelistingFields extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party ADD COLUMN seller_delisted_at TIMESTAMPTZ;
            ALTER TABLE party ADD COLUMN seller_delisted_reason TEXT;
            ALTER TABLE party ADD COLUMN seller_delisted_by_party_id UUID REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party DROP COLUMN IF EXISTS seller_delisted_at;
            ALTER TABLE party DROP COLUMN IF EXISTS seller_delisted_reason;
            ALTER TABLE party DROP COLUMN IF EXISTS seller_delisted_by_party_id;
        SQL);
    }
}
