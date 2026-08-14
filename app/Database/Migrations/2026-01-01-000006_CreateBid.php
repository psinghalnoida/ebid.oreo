<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateBid extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE bid (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                bidder_party_id CHAR(36) NOT NULL,

                amount              NUMERIC(14,2) NOT NULL,

                standing            ENUM('h1', 'h2', 'h3', 'outbid', 'defaulted', 'withdrawn') NOT NULL DEFAULT 'outbid',

                topup_required_by    DATETIME(6),

                topup_paid_at         DATETIME(6),

                defaulted_at           DATETIME(6),

                placed_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_bid_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_bid_bidder_party_id FOREIGN KEY (bidder_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_bid_sale_event ON bid (sale_event_id, amount DESC);
            CREATE INDEX idx_bid_bidder ON bid (bidder_party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS bid CASCADE;');
    }
}
