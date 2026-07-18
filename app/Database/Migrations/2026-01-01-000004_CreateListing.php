<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateListing extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE listing_status AS ENUM (
                'inventory', 'pending_approval', 'upcoming', 'active', 'sold', 'cycle_ended_unsold'
            );

            CREATE TABLE listing (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id               UUID NOT NULL REFERENCES tenant(id),
                seller_party_id         UUID NOT NULL REFERENCES party(id),
                physical_condition      TEXT NOT NULL,
                category                TEXT NOT NULL,
                subcategory              TEXT,
                quantity                 NUMERIC(12,2) NOT NULL,
                quantity_basis           TEXT NOT NULL DEFAULT 'unit',
                make_model                TEXT,
                yard_location_address     TEXT NOT NULL,
                yard_location_pin         VARCHAR(6) NOT NULL,
                inspector_party_id        UUID REFERENCES party(id),
                inspector_contact_note    TEXT,
                status                    listing_status NOT NULL DEFAULT 'inventory',
                rejection_reason           TEXT,
                superseded_by_listing_id   UUID REFERENCES listing(id),
                created_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                archived_at                 TIMESTAMPTZ
            );

            CREATE INDEX idx_listing_tenant_status ON listing (tenant_id, status) WHERE archived_at IS NULL;
            CREATE INDEX idx_listing_seller ON listing (seller_party_id) WHERE archived_at IS NULL;
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS listing CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS listing_status;');
    }
}
