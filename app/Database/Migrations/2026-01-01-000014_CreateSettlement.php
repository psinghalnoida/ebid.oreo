<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettlement extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE settlement_status AS ENUM ('pending', 'completed', 'stalled');

            CREATE TABLE settlement (
                id                       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id            UUID NOT NULL UNIQUE REFERENCES sale_event(id),
                buyer_party_id           UUID NOT NULL REFERENCES party(id),
                seller_party_id          UUID NOT NULL REFERENCES party(id),
                final_price              NUMERIC(14,2) NOT NULL,

                -- BR-33: all four must complete before formal closure
                seller_noc_confirmed_at   TIMESTAMPTZ,
                buyer_noc_confirmed_at    TIMESTAMPTZ,
                buyer_rated_seller_at     TIMESTAMPTZ,
                seller_rated_buyer_at     TIMESTAMPTZ,

                status                    settlement_status NOT NULL DEFAULT 'pending',

                -- BR-39: stall resolution tracking
                stall_flagged_at           TIMESTAMPTZ,
                forced_neutral_applied_at   TIMESTAMPTZ,

                created_at                   TIMESTAMPTZ NOT NULL DEFAULT now(),
                completed_at                  TIMESTAMPTZ
            );

            CREATE INDEX idx_settlement_status ON settlement (status);
            CREATE INDEX idx_settlement_buyer ON settlement (buyer_party_id);
            CREATE INDEX idx_settlement_seller ON settlement (seller_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS settlement CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS settlement_status;');
    }
}
