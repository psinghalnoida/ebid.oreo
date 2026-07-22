<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSellerApplication extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TYPE listing_status ADD VALUE IF NOT EXISTS 'suspended';

            CREATE TYPE seller_application_status AS ENUM ('pending', 'approved', 'rejected');

            CREATE TABLE seller_application (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id             UUID NOT NULL REFERENCES party(id),
                tenant_id             UUID NOT NULL REFERENCES tenant(id),
                status                 seller_application_status NOT NULL DEFAULT 'pending',
                rejection_reason        TEXT,
                decided_by_party_id      UUID REFERENCES party(id),
                applied_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                decided_at                  TIMESTAMPTZ,

                -- BR-09: a seller upgraded on one tenant has no automatic
                -- rights on another — one application per party per tenant
                UNIQUE (party_id, tenant_id)
            );

            CREATE INDEX idx_seller_application_tenant_status ON seller_application (tenant_id, status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS seller_application CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS seller_application_status;');
    }
}
