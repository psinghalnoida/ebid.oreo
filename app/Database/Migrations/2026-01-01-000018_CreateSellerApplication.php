<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateSellerApplication extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing MODIFY COLUMN status ENUM(
                'inventory', 'pending_approval', 'upcoming', 'active', 'sold',
                'cycle_ended_unsold', 'suspended'
            ) NOT NULL DEFAULT 'inventory';
            CREATE TABLE seller_application (

                id                  CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                tenant_id CHAR(36) NOT NULL,

                status                 ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',

                rejection_reason        TEXT,

                decided_by_party_id CHAR(36),

                applied_at                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                decided_at                  DATETIME(6),


                -- BR-09: a seller upgraded on one tenant has no automatic
                -- rights on another — one application per party per tenant
                UNIQUE (party_id, tenant_id),

                CONSTRAINT fk_seller_application_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_seller_application_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_seller_application_decided_by_party_id FOREIGN KEY (decided_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_seller_application_tenant_status ON seller_application (tenant_id, status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS seller_application CASCADE;');
    }
}
