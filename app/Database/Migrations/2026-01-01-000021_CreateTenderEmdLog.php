<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateTenderEmdLog extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tender_emd_log (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                amount                   NUMERIC(14,2) NOT NULL DEFAULT 0,

                payment_location_note      TEXT,

                no_emd_reason                 TEXT,

                logged_by_party_id CHAR(36) NOT NULL,

                logged_at                          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),


                CONSTRAINT chk_emd_audit_trail CHECK (
                    (amount > 0 AND payment_location_note IS NOT NULL)
                    OR (amount = 0 AND no_emd_reason IS NOT NULL)
                ),

                CONSTRAINT fk_tender_emd_log_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_emd_log_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_tender_emd_log_logged_by_party_id FOREIGN KEY (logged_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_tender_emd_log_sale_event ON tender_emd_log (sale_event_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tender_emd_log CASCADE;');
    }
}
