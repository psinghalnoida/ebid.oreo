<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBid extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE bid_standing AS ENUM ('h1', 'h2', 'h3', 'outbid', 'defaulted', 'withdrawn');

            CREATE TABLE bid (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id       UUID NOT NULL REFERENCES sale_event(id),
                bidder_party_id     UUID NOT NULL REFERENCES party(id),
                amount              NUMERIC(14,2) NOT NULL,
                standing            bid_standing NOT NULL DEFAULT 'outbid',
                topup_required_by    TIMESTAMPTZ,
                topup_paid_at         TIMESTAMPTZ,
                defaulted_at           TIMESTAMPTZ,
                placed_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_bid_sale_event ON bid (sale_event_id, amount DESC);
            CREATE INDEX idx_bid_bidder ON bid (bidder_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS bid CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS bid_standing;');
    }
}
