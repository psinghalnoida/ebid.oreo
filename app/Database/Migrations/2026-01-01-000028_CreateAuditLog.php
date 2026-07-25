<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLog extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE audit_log (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                occurred_at           TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
                event_type              TEXT NOT NULL,
                actor_party_id            UUID REFERENCES party(id),
                ip_address                 TEXT,
                user_agent                   TEXT,
                payload                        JSONB NOT NULL,
                previous_hash              TEXT NOT NULL,
                record_hash                  TEXT NOT NULL,
                sequence_number                 BIGSERIAL
            );

            CREATE INDEX idx_audit_log_occurred_at ON audit_log (occurred_at);
            CREATE INDEX idx_audit_log_actor ON audit_log (actor_party_id);
            CREATE INDEX idx_audit_log_event_type ON audit_log (event_type);
            CREATE INDEX idx_audit_log_sequence ON audit_log (sequence_number);

            REVOKE UPDATE, DELETE, TRUNCATE ON audit_log FROM ebidhub_app;
            GRANT INSERT, SELECT ON audit_log TO ebidhub_app;
        SQL);
    }

    public function down()
    {
        $this->db->query('GRANT UPDATE, DELETE, TRUNCATE ON audit_log TO ebidhub_app;');
        $this->db->query('DROP TABLE IF EXISTS audit_log CASCADE;');
    }
}
