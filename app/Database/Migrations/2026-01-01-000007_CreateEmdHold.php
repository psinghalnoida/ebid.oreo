<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateEmdHold extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE emd_hold (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                channel               ENUM('van', 'credit_card', 'manual_offline') NOT NULL,

                amount                 NUMERIC(14,2) NOT NULL,

                status                  ENUM('held', 'released', 'forfeited', 'refunded') NOT NULL DEFAULT 'held',

                recalculated_amount     NUMERIC(14,2),

                forfeited_to_tenant_amount   NUMERIC(14,2),

                forfeited_to_saas_amount      NUMERIC(14,2),

                forfeited_to_seller_amount    NUMERIC(14,2),

                gateway_reference       TEXT,

                held_at                  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                released_at               DATETIME(6),

                forfeited_at                DATETIME(6),

                CONSTRAINT fk_emd_hold_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_emd_hold_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_emd_sale_event ON emd_hold (sale_event_id);
            CREATE INDEX idx_emd_party ON emd_hold (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS emd_hold CASCADE;');
    }
}
