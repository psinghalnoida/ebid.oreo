<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateTenderReview extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tender_review (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                bid_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                round_number                INTEGER NOT NULL DEFAULT 1,

                status                        ENUM('provisional', 'extension_granted', 'rejected', 'confirmed') NOT NULL DEFAULT 'provisional',


                extension_reason                TEXT,

                extension_granted_by_party_id CHAR(36),

                extension_granted_at                  DATETIME(6),


                rejection_reason                        TEXT,

                rejected_by_party_id CHAR(36),

                rejected_at                                   DATETIME(6),


                confirmed_by_party_id CHAR(36),

                confirmed_at                                       DATETIME(6),


                created_at                                           DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_tender_review_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_review_bid_id FOREIGN KEY (bid_id) REFERENCES bid(id),

                CONSTRAINT fk_tender_review_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_tender_review_extension_granted_by_party_id FOREIGN KEY (extension_granted_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_tender_review_rejected_by_party_id FOREIGN KEY (rejected_by_party_id) REFERENCES party(id),


                CONSTRAINT fk_tender_review_confirmed_by_party_id FOREIGN KEY (confirmed_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_tender_review_sale_event ON tender_review (sale_event_id);
            CREATE INDEX idx_tender_review_status ON tender_review (sale_event_id, status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tender_review CASCADE;');
    }
}
