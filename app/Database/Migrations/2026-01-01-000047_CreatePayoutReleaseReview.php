<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50: "High-value pending payouts additionally require Tenant Admin or
// SaaS Admin review before release to a newly-changed account." This is
// the record of that extra gate — created instead of an immediate EMD
// release/settlement when a high-value payout would otherwise go out to
// a party whose bank details changed recently.
class CreatePayoutReleaseReview extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE payout_release_type AS ENUM ('plain_release', 'settlement_refund');
            CREATE TYPE payout_review_status AS ENUM ('pending', 'approved', 'declined');

            CREATE TABLE payout_release_review (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                emd_hold_id             UUID NOT NULL REFERENCES emd_hold(id),
                party_id                UUID NOT NULL REFERENCES party(id),
                amount                  NUMERIC(14,2) NOT NULL,
                release_type            payout_release_type NOT NULL,
                tenant_amount           NUMERIC(14,2),
                saas_amount             NUMERIC(14,2),
                buyer_refund            NUMERIC(14,2),
                status                  payout_review_status NOT NULL DEFAULT 'pending',
                reviewed_by_party_id    UUID REFERENCES party(id),
                decision_rationale      TEXT,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
                reviewed_at             TIMESTAMPTZ
            );

            CREATE INDEX idx_payout_review_status ON payout_release_review (status);
            CREATE INDEX idx_payout_review_party ON payout_release_review (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS payout_release_review CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS payout_review_status;');
        $this->db->query('DROP TYPE IF EXISTS payout_release_type;');
    }
}
