<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenant extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE tenant_class AS ENUM ('general', 'institutional', 'company_shop');

            CREATE TABLE tenant (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                name                    TEXT NOT NULL,
                tenant_class            tenant_class NOT NULL DEFAULT 'general',
                subdomain               TEXT UNIQUE,
                custom_domain           TEXT UNIQUE,
                saas_fee_percent        NUMERIC(4,2) NOT NULL DEFAULT 0.50
                                           CHECK (saas_fee_percent = 0.50),
                buyer_fee_percent       NUMERIC(4,2) NOT NULL DEFAULT 5.00
                                           CHECK (buyer_fee_percent >= 0),
                branding_logo_url       TEXT,
                branding_primary_color  TEXT,
                terms_url               TEXT,
                whitelisted_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                suspended_at            TIMESTAMPTZ,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE UNIQUE INDEX idx_tenant_subdomain ON tenant (subdomain) WHERE subdomain IS NOT NULL;
            CREATE UNIQUE INDEX idx_tenant_custom_domain ON tenant (custom_domain) WHERE custom_domain IS NOT NULL;
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tenant CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS tenant_class;');
    }
}
