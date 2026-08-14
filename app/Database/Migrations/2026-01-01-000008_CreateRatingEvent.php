<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateRatingEvent extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE rating_event (

                id                      CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                rating_role             ENUM('star_rating', 'seller_star_rating') NOT NULL,

                event_type              ENUM('upgrade', 'downgrade', 'forced_neutral') NOT NULL,

                previous_value          NUMERIC(2,1) NOT NULL,

                new_value                NUMERIC(2,1) NOT NULL CHECK (new_value >= 0 AND new_value <= 5),

                reason                    TEXT NOT NULL,

                status                     ENUM('applied', 'pending_tenant_approval', 'pending_super_admin_approval', 'rejected') NOT NULL DEFAULT 'applied',

                tenant_admin_approved_by CHAR(36),

                tenant_admin_approved_at    DATETIME(6),

                super_admin_approved_by CHAR(36),

                super_admin_approved_at      DATETIME(6),

                appealed_at                   DATETIME(6),

                appeal_outcome                 TEXT,

                related_sale_event_id CHAR(36),

                created_at                       DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                applied_at                        DATETIME(6),

                CONSTRAINT fk_rating_event_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_rating_event_tenant_admin_approved_by FOREIGN KEY (tenant_admin_approved_by) REFERENCES party(id),

                CONSTRAINT fk_rating_event_super_admin_approved_by FOREIGN KEY (super_admin_approved_by) REFERENCES party(id),

                CONSTRAINT fk_rating_event_related_sale_event_id FOREIGN KEY (related_sale_event_id) REFERENCES sale_event(id)
            );

            CREATE INDEX idx_rating_event_party ON rating_event (party_id, rating_role, created_at DESC);
            CREATE INDEX idx_rating_event_pending ON rating_event (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS rating_event CASCADE;');
    }
}
