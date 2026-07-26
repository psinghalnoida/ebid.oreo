<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHighValueDisposalRecord extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE high_value_disposal_record (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                settlement_id       UUID NOT NULL UNIQUE REFERENCES settlement(id),
                sale_event_id       UUID NOT NULL REFERENCES sale_event(id),
                tenant_id           UUID NOT NULL REFERENCES tenant(id),
                sale_format         TEXT NOT NULL,
                reserve_value       NUMERIC(14,2),
                final_sale_value    NUMERIC(14,2) NOT NULL,
                variance            NUMERIC(14,2) NOT NULL,
                created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_hvdr_tenant ON high_value_disposal_record (tenant_id);
            CREATE INDEX idx_hvdr_created_at ON high_value_disposal_record (created_at);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS high_value_disposal_record CASCADE;');
    }
}
