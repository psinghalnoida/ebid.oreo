<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateOffer extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE offer (

                id                      CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                buyer_party_id CHAR(36) NOT NULL,

                amount                  NUMERIC(14,2) NOT NULL,

                status                  ENUM('submitted', 'accepted', 'rejected', 'withdrawn', 'lapsed') NOT NULL DEFAULT 'submitted',


                -- BR-42: mandatory, closed-list reason whenever the seller
                -- accepts an offer other than the highest received
                seller_selection_reason  TEXT,


                withdrawal_reason         TEXT,


                created_at                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                decided_at                  DATETIME(6),

                withdrawn_at                 DATETIME(6),

                CONSTRAINT fk_offer_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_offer_buyer_party_id FOREIGN KEY (buyer_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_offer_sale_event ON offer (sale_event_id, amount DESC);
            CREATE INDEX idx_offer_buyer ON offer (buyer_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS offer CASCADE;');
    }
}
