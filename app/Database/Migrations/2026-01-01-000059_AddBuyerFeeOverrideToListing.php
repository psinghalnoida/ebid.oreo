<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-32: "the Tenant Admin can adjust the buyer's transaction fee on
// any active listing" -- distinct from the tenant-wide default
// (tenant.buyer_fee_percent), which only sets the blanket fallback.
class AddBuyerFeeOverrideToListing extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing ADD COLUMN buyer_fee_percent_override NUMERIC(4,2);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing DROP COLUMN buyer_fee_percent_override;
        SQL);
    }
}
