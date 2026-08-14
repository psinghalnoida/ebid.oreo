<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateHighValueDisposalRecord extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE high_value_disposal_record (

                id                  CHAR(36) PRIMARY KEY,

                settlement_id CHAR(36) NOT NULL UNIQUE,

                sale_event_id CHAR(36) NOT NULL,

                tenant_id CHAR(36) NOT NULL,

                sale_format         TEXT NOT NULL,

                reserve_value       NUMERIC(14,2),

                final_sale_value    NUMERIC(14,2) NOT NULL,

                variance            NUMERIC(14,2) NOT NULL,

                created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_high_value_disposal_record_settlement_id FOREIGN KEY (settlement_id) REFERENCES settlement(id),

                CONSTRAINT fk_high_value_disposal_record_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_high_value_disposal_record_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id)
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
