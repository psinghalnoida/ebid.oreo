<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateInvoice extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE invoice (

                id                CHAR(36) PRIMARY KEY,

                settlement_id CHAR(36) NOT NULL,

                invoice_type      ENUM('tenant_to_buyer', 'saas_to_tenant') NOT NULL,

                invoice_number    VARCHAR(255) NOT NULL UNIQUE,

                issued_by_name    TEXT NOT NULL,

                issued_to_name    TEXT NOT NULL,

                base_amount       NUMERIC(14,2) NOT NULL,

                gst_rate_percent  NUMERIC(5,2) NOT NULL,

                gst_amount        NUMERIC(14,2) NOT NULL,

                total_amount      NUMERIC(14,2) NOT NULL,

                created_at        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_invoice_settlement_id FOREIGN KEY (settlement_id) REFERENCES settlement(id)
            );

            CREATE INDEX idx_invoice_settlement ON invoice (settlement_id);
        SQL);

        // Tamper-evidence via privilege restriction (REVOKE UPDATE/DELETE/
        // TRUNCATE ... GRANT INSERT/SELECT) not reproduced on MySQL -- see
        // CreateAuditLog.php's up() for the full empirically-confirmed
        // reasoning (MySQL's partial_revokes only carves database-level
        // restrictions out of global grants, not table-level out of
        // database-level). invoice has no hash-chain equivalent to
        // audit_log's, so this table has no compensating tamper-evidence
        // layer on MySQL -- a real, accepted limitation, documented in
        // docs/DECISIONS.md.
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS invoice CASCADE;');
    }
}
