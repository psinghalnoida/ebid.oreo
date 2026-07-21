<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOffer extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE offer_status AS ENUM ('submitted', 'accepted', 'rejected', 'withdrawn', 'lapsed');

            CREATE TABLE offer (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id           UUID NOT NULL REFERENCES sale_event(id),
                buyer_party_id          UUID NOT NULL REFERENCES party(id),
                amount                  NUMERIC(14,2) NOT NULL,
                status                  offer_status NOT NULL DEFAULT 'submitted',

                -- BR-42: mandatory, closed-list reason whenever the seller
                -- accepts an offer other than the highest received
                seller_selection_reason  TEXT,

                withdrawal_reason         TEXT,

                created_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                decided_at                  TIMESTAMPTZ,
                withdrawn_at                 TIMESTAMPTZ
            );

            CREATE INDEX idx_offer_sale_event ON offer (sale_event_id, amount DESC);
            CREATE INDEX idx_offer_buyer ON offer (buyer_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS offer CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS offer_status;');
    }
}
