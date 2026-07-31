<?php

namespace App\Database\Migrations;

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
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE trading_session_chronicle (
                id                          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id               UUID NOT NULL REFERENCES sale_event(id),
                settlement_id               UUID NOT NULL REFERENCES settlement(id),
                tenant_id                   UUID NOT NULL REFERENCES tenant(id),
                reference_number            TEXT NOT NULL UNIQUE,
                verification_token          TEXT NOT NULL UNIQUE,
                version                     INTEGER NOT NULL DEFAULT 1,
                superseded_by_chronicle_id  UUID REFERENCES trading_session_chronicle(id),
                content_hash                TEXT NOT NULL,
                report_data                 TEXT NOT NULL,
                generated_at                TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_chronicle_sale_event ON trading_session_chronicle (sale_event_id);
            CREATE INDEX idx_chronicle_tenant ON trading_session_chronicle (tenant_id);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            DROP TABLE IF EXISTS trading_session_chronicle;
        SQL);
    }
}
