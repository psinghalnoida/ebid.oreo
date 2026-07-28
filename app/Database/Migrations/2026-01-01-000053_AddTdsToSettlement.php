<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-53: Tax Deduction at Source, Section 194-O. The BR text explicitly
// leaves the rate open pending tax-advisor confirmation — the Super
// Admin (project owner) has now confirmed 10% for this platform, so
// this is no longer a DEV-ONLY placeholder. Deducted on the GROSS sale
// amount, from what the platform owes the seller — separate from the
// buyer-side commission (BR-27/BR-33's tenant/SaaS fee split) and from
// the BR-56 GST invoice, which covers commission only, not TDS.
class AddTdsToSettlement extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE settlement
                ADD COLUMN tds_rate_percent NUMERIC(5,2),
                ADD COLUMN tds_amount        NUMERIC(14,2);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE settlement
                DROP COLUMN IF EXISTS tds_rate_percent,
                DROP COLUMN IF EXISTS tds_amount;
        SQL);
    }
}
