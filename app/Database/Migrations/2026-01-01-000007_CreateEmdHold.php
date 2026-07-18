<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmdHold extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE emd_channel AS ENUM ('van', 'credit_card', 'manual_offline');
            CREATE TYPE emd_status AS ENUM ('held', 'released', 'forfeited', 'refunded');

            CREATE TABLE emd_hold (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                party_id             UUID NOT NULL REFERENCES party(id),
                channel               emd_channel NOT NULL,
                amount                 NUMERIC(14,2) NOT NULL,
                status                  emd_status NOT NULL DEFAULT 'held',
                recalculated_amount     NUMERIC(14,2),
                forfeited_to_tenant_amount   NUMERIC(14,2),
                forfeited_to_saas_amount      NUMERIC(14,2),
                forfeited_to_seller_amount    NUMERIC(14,2),
                gateway_reference       TEXT,
                held_at                  TIMESTAMPTZ NOT NULL DEFAULT now(),
                released_at               TIMESTAMPTZ,
                forfeited_at                TIMESTAMPTZ
            );

            CREATE INDEX idx_emd_sale_event ON emd_hold (sale_event_id);
            CREATE INDEX idx_emd_party ON emd_hold (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS emd_hold CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS emd_status;');
        $this->db->query('DROP TYPE IF EXISTS emd_channel;');
    }
}
