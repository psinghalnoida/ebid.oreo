<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenderEmdLog extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE tender_emd_log (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                party_id               UUID NOT NULL REFERENCES party(id),
                amount                   NUMERIC(14,2) NOT NULL DEFAULT 0,
                payment_location_note      TEXT,
                no_emd_reason                 TEXT,
                logged_by_party_id               UUID NOT NULL REFERENCES party(id),
                logged_at                          TIMESTAMPTZ NOT NULL DEFAULT now(),

                CONSTRAINT chk_emd_audit_trail CHECK (
                    (amount > 0 AND payment_location_note IS NOT NULL)
                    OR (amount = 0 AND no_emd_reason IS NOT NULL)
                )
            );

            CREATE INDEX idx_tender_emd_log_sale_event ON tender_emd_log (sale_event_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tender_emd_log CASCADE;');
    }
}
