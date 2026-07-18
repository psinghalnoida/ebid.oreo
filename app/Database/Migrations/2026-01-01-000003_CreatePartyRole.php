<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePartyRole extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE party_role_type AS ENUM (
                'buyer', 'seller', 'bidder', 'vendor', 'customer',
                'auctioneer', 'service_provider', 'surveyor', 'financier',
                'tenant_admin'
            );

            CREATE TABLE party_role (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id        UUID NOT NULL REFERENCES party(id),
                role            party_role_type NOT NULL,
                tenant_id       UUID REFERENCES tenant(id),
                granted_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                revoked_at      TIMESTAMPTZ,
                created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT uq_active_role UNIQUE NULLS NOT DISTINCT (party_id, role, tenant_id, revoked_at)
            );

            CREATE INDEX idx_party_role_party ON party_role (party_id) WHERE revoked_at IS NULL;
            CREATE INDEX idx_party_role_tenant_role ON party_role (tenant_id, role) WHERE revoked_at IS NULL;

            CREATE UNIQUE INDEX uq_one_active_tenant_admin
                ON party_role (tenant_id)
                WHERE role = 'tenant_admin' AND revoked_at IS NULL;
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_role CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS party_role_type;');
    }
}
