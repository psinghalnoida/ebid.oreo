<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreatePartyRole extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE party_role (

                id              CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                role            ENUM(
                'buyer', 'seller', 'bidder', 'vendor', 'customer',
                'auctioneer', 'service_provider', 'surveyor', 'financier',
                'tenant_admin'
            ) NOT NULL,

                tenant_id CHAR(36),

                granted_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                revoked_at      DATETIME(6),

                created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),


                -- Original constraint: UNIQUE NULLS NOT DISTINCT (party_id, role,
                -- tenant_id, revoked_at) -- Postgres 15+ syntax with no MySQL
                -- equivalent, and MySQL's *default* unique-index behavior is the
                -- opposite of what's needed here (MySQL treats NULLs as distinct,
                -- same as plain Postgres UNIQUE; this constraint deliberately
                -- wants NULLs in tenant_id/revoked_at to collide, so a party can't
                -- hold two rows for the same party_id/role/tenant_id while both
                -- are "active", i.e. revoked_at IS NULL). Reproduced by coalescing
                -- the nullable columns to fixed sentinel values in generated
                -- columns, then keying the UNIQUE constraint off those instead.
                tenant_id_key   CHAR(36) GENERATED ALWAYS AS (
                    COALESCE(tenant_id, '00000000-0000-0000-0000-000000000000')
                ) STORED,

                revoked_at_key  DATETIME(6) GENERATED ALWAYS AS (
                    COALESCE(revoked_at, '1970-01-01 00:00:00')
                ) STORED,

                UNIQUE KEY uq_active_role (party_id, role, tenant_id_key, revoked_at_key),


                -- BR-?: at most one active tenant_admin per tenant. MySQL has no
                -- partial/filtered unique index -- see CreateSaleEvent.php's
                -- open_sale_marker for the same generated-marker-column technique.
                active_tenant_admin_marker CHAR(1) GENERATED ALWAYS AS (
                    CASE WHEN role = 'tenant_admin' AND revoked_at IS NULL THEN 'Y' END
                ) STORED,

                UNIQUE KEY uq_one_active_tenant_admin (tenant_id, active_tenant_admin_marker),

                CONSTRAINT fk_party_role_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_party_role_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id)
            );

            CREATE INDEX idx_party_role_party ON party_role (party_id);
            CREATE INDEX idx_party_role_tenant_role ON party_role (tenant_id, role);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_role CASCADE;');
    }
}
