<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSaleEvent extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE sale_format AS ENUM ('buy_now', 'express', 'easy', 'tender');
            CREATE TYPE sale_event_status AS ENUM (
                'pending_approval', 'grace_period', 'active', 'closed_sold',
                'cancelled', 'cycle_ended_unsold'
            );

            CREATE TABLE sale_event (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                listing_id              UUID NOT NULL REFERENCES listing(id),
                tenant_id               UUID NOT NULL REFERENCES tenant(id),
                ern                     TEXT NOT NULL UNIQUE,
                sale_format             sale_format NOT NULL,
                status                  sale_event_status NOT NULL DEFAULT 'pending_approval',
                expected_value           NUMERIC(14,2),
                reserve_value             NUMERIC(14,2),
                emd_percent               NUMERIC(4,2) NOT NULL DEFAULT 10.00
                                             CHECK (emd_percent = 10.00),
                dynamic_time_trigger_minutes    INTEGER DEFAULT 10,
                dynamic_time_extension_minutes  INTEGER DEFAULT 2,
                intensity_mode_active            BOOLEAN NOT NULL DEFAULT false,
                result_mode                TEXT CHECK (result_mode IN ('instant_close', 'approval_required')),
                current_price              NUMERIC(14,2),
                current_high_bidder_party_id UUID REFERENCES party(id),
                grace_period_ends_at        TIMESTAMPTZ,
                scheduled_start_at          TIMESTAMPTZ,
                scheduled_end_at             TIMESTAMPTZ,
                actual_closed_at              TIMESTAMPTZ,
                rejection_reason              TEXT,
                emergency_stopped_at           TIMESTAMPTZ,
                emergency_stop_reason          TEXT,
                created_at                     TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                     TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE UNIQUE INDEX uq_one_open_sale_event_per_listing
                ON sale_event (listing_id)
                WHERE status IN ('pending_approval', 'grace_period', 'active');

            CREATE INDEX idx_sale_event_tenant_status ON sale_event (tenant_id, status);
            CREATE INDEX idx_sale_event_listing ON sale_event (listing_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS sale_event CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS sale_event_status;');
        $this->db->query('DROP TYPE IF EXISTS sale_format;');
    }
}
