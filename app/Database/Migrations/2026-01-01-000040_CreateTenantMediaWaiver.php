<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateTenantMediaWaiver extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tenant_media_waiver (

                id                     CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                category               VARCHAR(255) NOT NULL,

                business_justification TEXT NOT NULL,

                status                 ENUM('pending', 'approved', 'declined', 'lapsed', 'revoked') NOT NULL DEFAULT 'pending',

                requested_by_party_id CHAR(36) NOT NULL,

                decided_by_party_id CHAR(36),

                decision_rationale     TEXT,

                granted_at             DATETIME(6),

                expires_at             DATETIME(6),

                revoked_reason         TEXT,

                created_at             DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at             DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_tenant_media_waiver_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_tenant_media_waiver_requested_by_party_id FOREIGN KEY (requested_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_tenant_media_waiver_decided_by_party_id FOREIGN KEY (decided_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_tmw_tenant_category ON tenant_media_waiver (tenant_id, category);
            CREATE INDEX idx_tmw_status ON tenant_media_waiver (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tenant_media_waiver CASCADE;');
    }
}
