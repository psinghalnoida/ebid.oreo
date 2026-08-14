<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateConsentEvent extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE consent_event (

                id                    CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                consent_type          VARCHAR(255) NOT NULL,

                terms_version         TEXT NOT NULL,

                related_reference_id  CHAR(36),

                consent_text_shown    TEXT NOT NULL,

                ip_address            TEXT,

                created_at            DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_consent_event_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_consent_event_party ON consent_event (party_id);
            CREATE INDEX idx_consent_event_type ON consent_event (consent_type);
        SQL);

        // Tamper-evidence via privilege restriction (REVOKE UPDATE/DELETE/
        // TRUNCATE ... GRANT INSERT/SELECT) not reproduced on MySQL -- see
        // CreateAuditLog.php's up() for the full empirically-confirmed
        // reasoning (MySQL's partial_revokes only carves database-level
        // restrictions out of global grants, not table-level out of
        // database-level). consent_event has no hash-chain equivalent to
        // audit_log's, so this table has no compensating tamper-evidence
        // layer on MySQL -- a real, accepted limitation, documented in
        // docs/DECISIONS.md.
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS consent_event CASCADE;');
    }
}
