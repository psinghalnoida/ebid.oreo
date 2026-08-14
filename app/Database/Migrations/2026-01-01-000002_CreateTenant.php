<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateTenant extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tenant (
                id                      CHAR(36) PRIMARY KEY,
                name                    TEXT NOT NULL,
                tenant_class            ENUM('general', 'institutional', 'company_shop') NOT NULL DEFAULT 'general',
                -- VARCHAR(255), not TEXT: MySQL requires an explicit key-length
                -- prefix to index/UNIQUE a TEXT column; these are short domain
                -- identifiers, so a bounded VARCHAR is both correct and
                -- directly indexable. Uniqueness enforced by the named indexes
                -- below, not an inline UNIQUE, to avoid a redundant second
                -- unique index on the same column.
                subdomain               VARCHAR(255),
                custom_domain           VARCHAR(255),
                saas_fee_percent        NUMERIC(4,2) NOT NULL DEFAULT 0.50
                                           CHECK (saas_fee_percent = 0.50),
                buyer_fee_percent       NUMERIC(4,2) NOT NULL DEFAULT 5.00
                                           CHECK (buyer_fee_percent >= 0),
                branding_logo_url       TEXT,
                branding_primary_color  TEXT,
                terms_url               TEXT,
                whitelisted_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                suspended_at            DATETIME(6),
                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
            );

            CREATE UNIQUE INDEX idx_tenant_subdomain ON tenant (subdomain);
            CREATE UNIQUE INDEX idx_tenant_custom_domain ON tenant (custom_domain);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tenant CASCADE;');
    }
}
