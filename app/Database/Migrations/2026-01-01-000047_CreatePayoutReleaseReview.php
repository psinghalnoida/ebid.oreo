<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-50: "High-value pending payouts additionally require Tenant Admin or
// SaaS Admin review before release to a newly-changed account." This is
// the record of that extra gate — created instead of an immediate EMD
// release/settlement when a high-value payout would otherwise go out to
// a party whose bank details changed recently.
class CreatePayoutReleaseReview extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE payout_release_review (

                id                      CHAR(36) PRIMARY KEY,

                emd_hold_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                amount                  NUMERIC(14,2) NOT NULL,

                release_type            ENUM('plain_release', 'settlement_refund') NOT NULL,

                tenant_amount           NUMERIC(14,2),

                saas_amount             NUMERIC(14,2),

                buyer_refund            NUMERIC(14,2),

                status                  ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',

                reviewed_by_party_id CHAR(36),

                decision_rationale      TEXT,

                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                reviewed_at             DATETIME(6),

                CONSTRAINT fk_payout_release_review_emd_hold_id FOREIGN KEY (emd_hold_id) REFERENCES emd_hold(id),

                CONSTRAINT fk_payout_release_review_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_payout_release_review_reviewed_by_party_id FOREIGN KEY (reviewed_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_payout_review_status ON payout_release_review (status);
            CREATE INDEX idx_payout_review_party ON payout_release_review (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS payout_release_review CASCADE;');
    }
}
