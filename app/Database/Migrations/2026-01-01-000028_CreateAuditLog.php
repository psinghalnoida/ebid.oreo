<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateAuditLog extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE audit_log (

                id                  CHAR(36) PRIMARY KEY,

                occurred_at           DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                event_type              VARCHAR(255) NOT NULL,

                actor_party_id CHAR(36),

                ip_address                 TEXT,

                user_agent                   TEXT,

                payload                        TEXT NOT NULL,

                previous_hash              TEXT NOT NULL,

                record_hash                  TEXT NOT NULL,


                -- BIGSERIAL -> MySQL BIGINT AUTO_INCREMENT. Unlike Postgres,
                -- MySQL requires an AUTO_INCREMENT column to be indexed, and
                -- inline UNIQUE here does double duty as that required index
                -- and as idx_audit_log_sequence (kept separately below in the
                -- other tables of this style for a plain, non-unique index,
                -- but here the column is inherently unique by definition).
                sequence_number                 BIGINT NOT NULL AUTO_INCREMENT UNIQUE,

                CONSTRAINT fk_audit_log_actor_party_id FOREIGN KEY (actor_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_audit_log_occurred_at ON audit_log (occurred_at);
            CREATE INDEX idx_audit_log_actor ON audit_log (actor_party_id);
            CREATE INDEX idx_audit_log_event_type ON audit_log (event_type);
        SQL);

        // Tamper-evidence via privilege restriction (the original Postgres
        // migration's REVOKE UPDATE/DELETE/TRUNCATE ... GRANT INSERT/SELECT)
        // is NOT reproduced on MySQL -- confirmed empirically not achievable:
        // MySQL's partial_revokes system variable only supports carving a
        // *database*-level restriction out of a *global* (*.*) grant, not a
        // *table*-level restriction out of a *database*-level (ebidhub.*)
        // grant (REVOKE UPDATE, DELETE ON ebidhub.audit_log FROM ebidhub_app
        // fails with "There is no such grant defined" regardless). The only
        // way to get real table-level restriction in MySQL is to never grant
        // database-wide in the first place and instead enumerate UPDATE/
        // DELETE grants per table explicitly -- rejected here as a fragile,
        // high-maintenance pattern that would silently break app access to
        // any future table whose migration forgets to also grant it.
        //
        // This does not weaken the actual tamper-EVIDENCE guarantee: that is
        // the SHA-256 hash chain (AuditLogService::verifyChainIntegrity()),
        // which detects any row tampering after the fact regardless of
        // which privileges the app's DB connection holds. The REVOKE/GRANT
        // was always a secondary, defense-in-depth layer (prevent, not just
        // detect) -- its absence on MySQL is a real, accepted limitation,
        // documented in docs/DECISIONS.md, not a silent gap.
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS audit_log CASCADE;');
    }
}
