<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50(c): "High-value pending payouts additionally require Tenant Admin
// or SaaS Admin review before release to a newly-changed account." One row
// per (settlement, bank_account) pair that ever needed review — once
// 'released', that bank_account is treated as vetted for future payouts
// too (see PayoutAccountService::evaluatePayout).
class CreatePayoutHold extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE payout_hold_status AS ENUM ('pending', 'released', 'rejected');

            CREATE TABLE payout_hold (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                settlement_id           UUID NOT NULL REFERENCES settlement(id),
                party_id                UUID NOT NULL REFERENCES party(id),
                bank_account_id         UUID NOT NULL REFERENCES party_bank_account(id),
                amount                  NUMERIC(14,2) NOT NULL,
                status                  payout_hold_status NOT NULL DEFAULT 'pending',
                reviewed_by_party_id    UUID REFERENCES party(id),
                review_notes            TEXT,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
                reviewed_at             TIMESTAMPTZ
            );

            CREATE INDEX idx_payout_hold_status ON payout_hold (status);
            CREATE INDEX idx_payout_hold_settlement ON payout_hold (settlement_id);
            CREATE INDEX idx_payout_hold_bank_account ON payout_hold (bank_account_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS payout_hold CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS payout_hold_status;');
    }
}
