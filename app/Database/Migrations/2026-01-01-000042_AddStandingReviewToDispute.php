<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddStandingReviewToDispute extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->db->query("ALTER TABLE dispute MODIFY COLUMN category ENUM(
            'payment', 'condition_delivery', 'non_lifting_collection',
            'auction_rejection', 'buyer_non_response', 'standing_review'
        ) NOT NULL;");
        $this->execMulti(<<<SQL
            ALTER TABLE dispute MODIFY COLUMN sale_event_id CHAR(36);
            ALTER TABLE dispute MODIFY COLUMN filed_by_party_id CHAR(36);

            ALTER TABLE party ADD COLUMN standing_review_complaint_count INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN standing_review_cbs_offense_count INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN standing_review_next_annual_at DATETIME(6);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party DROP COLUMN standing_review_complaint_count;
            ALTER TABLE party DROP COLUMN standing_review_cbs_offense_count;
            ALTER TABLE party DROP COLUMN standing_review_next_annual_at;
            ALTER TABLE dispute MODIFY COLUMN sale_event_id CHAR(36) NOT NULL;
            ALTER TABLE dispute MODIFY COLUMN filed_by_party_id CHAR(36) NOT NULL;
        SQL);
    }
}
