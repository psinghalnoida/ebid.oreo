<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInvoice extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE invoice_type AS ENUM ('tenant_to_buyer', 'saas_to_tenant');

            CREATE TABLE invoice (
                id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                settlement_id     UUID NOT NULL REFERENCES settlement(id),
                invoice_type      invoice_type NOT NULL,
                invoice_number    TEXT NOT NULL UNIQUE,
                issued_by_name    TEXT NOT NULL,
                issued_to_name    TEXT NOT NULL,
                base_amount       NUMERIC(14,2) NOT NULL,
                gst_rate_percent  NUMERIC(5,2) NOT NULL,
                gst_amount        NUMERIC(14,2) NOT NULL,
                total_amount      NUMERIC(14,2) NOT NULL,
                created_at        TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_invoice_settlement ON invoice (settlement_id);

            REVOKE UPDATE, DELETE, TRUNCATE ON invoice FROM ebidhub_app;
            GRANT INSERT, SELECT ON invoice TO ebidhub_app;
        SQL);
    }

    public function down()
    {
        $this->db->query('GRANT UPDATE, DELETE, TRUNCATE ON invoice TO ebidhub_app;');
        $this->db->query('DROP TABLE IF EXISTS invoice CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS invoice_type;');
    }
}
