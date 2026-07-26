<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenantMediaWaiver extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE tenant_media_waiver_status AS ENUM ('pending', 'approved', 'declined', 'lapsed', 'revoked');

            CREATE TABLE tenant_media_waiver (
                id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                tenant_id              UUID NOT NULL REFERENCES tenant(id),
                category               TEXT NOT NULL,
                business_justification TEXT NOT NULL,
                status                 tenant_media_waiver_status NOT NULL DEFAULT 'pending',
                requested_by_party_id  UUID NOT NULL REFERENCES party(id),
                decided_by_party_id    UUID REFERENCES party(id),
                decision_rationale     TEXT,
                granted_at             TIMESTAMPTZ,
                expires_at             TIMESTAMPTZ,
                revoked_reason         TEXT,
                created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at             TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_tmw_tenant_category ON tenant_media_waiver (tenant_id, category);
            CREATE INDEX idx_tmw_status ON tenant_media_waiver (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tenant_media_waiver CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS tenant_media_waiver_status;');
    }
}
