<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateListing extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE listing (

                id                      CHAR(36) PRIMARY KEY,

                tenant_id CHAR(36) NOT NULL,

                seller_party_id CHAR(36) NOT NULL,

                physical_condition      TEXT NOT NULL,

                category                TEXT NOT NULL,

                subcategory              TEXT,

                quantity                 NUMERIC(12,2) NOT NULL,

                quantity_basis           VARCHAR(255) NOT NULL DEFAULT 'unit',

                make_model                TEXT,

                yard_location_address     TEXT NOT NULL,

                yard_location_pin         VARCHAR(6) NOT NULL,

                inspector_party_id CHAR(36),

                inspector_contact_note    TEXT,

                status                    ENUM(
                'inventory', 'pending_approval', 'upcoming', 'active', 'sold', 'cycle_ended_unsold'
            ) NOT NULL DEFAULT 'inventory',

                rejection_reason           TEXT,

                superseded_by_listing_id CHAR(36),

                created_at                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                archived_at                 DATETIME(6),

                CONSTRAINT fk_listing_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_listing_seller_party_id FOREIGN KEY (seller_party_id) REFERENCES party(id),

                CONSTRAINT fk_listing_inspector_party_id FOREIGN KEY (inspector_party_id) REFERENCES party(id),

                CONSTRAINT fk_listing_superseded_by_listing_id FOREIGN KEY (superseded_by_listing_id) REFERENCES listing(id)
            );

            CREATE INDEX idx_listing_tenant_status ON listing (tenant_id, status);
            CREATE INDEX idx_listing_seller ON listing (seller_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS listing CASCADE;');
    }
}
