<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-18: "Every patron maintains up to four address records:
// Registered, Billing, Correspondence, and Site/Yard" — one row per
// type per party (UNIQUE constraint), not an open-ended list.
class CreatePartyAddress extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE party_address_type AS ENUM ('registered', 'billing', 'correspondence', 'site_yard');

            CREATE TABLE party_address (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id        UUID NOT NULL REFERENCES party(id),
                address_type    party_address_type NOT NULL,
                line1           TEXT NOT NULL,
                line2           TEXT,
                city            TEXT NOT NULL,
                district        TEXT NOT NULL,
                state           TEXT NOT NULL,
                country         TEXT NOT NULL DEFAULT 'India',
                pin_code        VARCHAR(6) NOT NULL,
                gps_lat         NUMERIC(9,6),
                gps_lng         NUMERIC(9,6),
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (party_id, address_type)
            );

            CREATE INDEX idx_party_address_party ON party_address (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_address CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS party_address_type;');
    }
}
