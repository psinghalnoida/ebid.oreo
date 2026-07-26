<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStandingReviewToDispute extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TYPE dispute_category ADD VALUE 'standing_review';");
        $this->db->query(<<<SQL
            ALTER TABLE dispute ALTER COLUMN sale_event_id DROP NOT NULL;
            ALTER TABLE dispute ALTER COLUMN filed_by_party_id DROP NOT NULL;

            ALTER TABLE party ADD COLUMN standing_review_complaint_count INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN standing_review_cbs_offense_count INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN standing_review_next_annual_at TIMESTAMPTZ;
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party DROP COLUMN IF EXISTS standing_review_complaint_count;
            ALTER TABLE party DROP COLUMN IF EXISTS standing_review_cbs_offense_count;
            ALTER TABLE party DROP COLUMN IF EXISTS standing_review_next_annual_at;
            ALTER TABLE dispute ALTER COLUMN sale_event_id SET NOT NULL;
            ALTER TABLE dispute ALTER COLUMN filed_by_party_id SET NOT NULL;
        SQL);
    }
}
