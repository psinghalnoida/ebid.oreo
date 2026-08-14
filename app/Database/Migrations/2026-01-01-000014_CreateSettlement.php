<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateSettlement extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE settlement (

                id                       CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL UNIQUE,

                buyer_party_id CHAR(36) NOT NULL,

                seller_party_id CHAR(36) NOT NULL,

                final_price              NUMERIC(14,2) NOT NULL,


                -- BR-33: all four must complete before formal closure
                seller_noc_confirmed_at   DATETIME(6),

                buyer_noc_confirmed_at    DATETIME(6),

                buyer_rated_seller_at     DATETIME(6),

                seller_rated_buyer_at     DATETIME(6),


                status                    ENUM('pending', 'completed', 'stalled') NOT NULL DEFAULT 'pending',


                -- BR-39: stall resolution tracking
                stall_flagged_at           DATETIME(6),

                forced_neutral_applied_at   DATETIME(6),


                created_at                   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                completed_at                  DATETIME(6),

                CONSTRAINT fk_settlement_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_settlement_buyer_party_id FOREIGN KEY (buyer_party_id) REFERENCES party(id),

                CONSTRAINT fk_settlement_seller_party_id FOREIGN KEY (seller_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_settlement_status ON settlement (status);
            CREATE INDEX idx_settlement_buyer ON settlement (buyer_party_id);
            CREATE INDEX idx_settlement_seller ON settlement (seller_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS settlement CASCADE;');
    }
}
