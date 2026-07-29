<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-32: "the Tenant Admin can adjust the buyer's transaction fee on
// any active listing" -- distinct from the tenant-wide default
// (tenant.buyer_fee_percent), which only sets the blanket fallback.
class AddBuyerFeeOverrideToListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing ADD COLUMN buyer_fee_percent_override NUMERIC(4,2);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE listing DROP COLUMN IF EXISTS buyer_fee_percent_override;
        SQL);
    }
}
