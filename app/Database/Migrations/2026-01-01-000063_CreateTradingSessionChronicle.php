<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Section 7.10 (ADWITIX_Master.docx): the Trading Session Chronicle --
// a system-authenticated report generated automatically the moment a
// Sale Event's Settlement completes. report_data is a JSON snapshot
// captured at generation time (not re-derived live on every render),
// so a certified Chronicle stays exactly what it was when certified
// even if later activity touches the same Sale Event -- the same
// "once certified, immutable; a correction creates a new version"
// principle BR-13 already applies to Listings.
class CreateTradingSessionChronicle extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE trading_session_chronicle (

                id                          CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                settlement_id CHAR(36) NOT NULL,

                tenant_id CHAR(36) NOT NULL,

                reference_number            VARCHAR(255) NOT NULL UNIQUE,

                verification_token          VARCHAR(255) NOT NULL UNIQUE,

                version                     INTEGER NOT NULL DEFAULT 1,

                superseded_by_chronicle_id CHAR(36),

                content_hash                TEXT NOT NULL,

                report_data                 TEXT NOT NULL,

                generated_at                DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_trading_session_chronicle_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_trading_session_chronicle_settlement_id FOREIGN KEY (settlement_id) REFERENCES settlement(id),

                CONSTRAINT fk_trading_session_chronicle_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_trading_session_chronicle_superseded_by_chronicle_id FOREIGN KEY (superseded_by_chronicle_id) REFERENCES trading_session_chronicle(id)
            );

            CREATE INDEX idx_chronicle_sale_event ON trading_session_chronicle (sale_event_id);
            CREATE INDEX idx_chronicle_tenant ON trading_session_chronicle (tenant_id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            DROP TABLE IF EXISTS trading_session_chronicle;
        SQL);
    }
}
