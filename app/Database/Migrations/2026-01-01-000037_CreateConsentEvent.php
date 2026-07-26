<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConsentEvent extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE consent_event (
                id                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id              UUID NOT NULL REFERENCES party(id),
                consent_type          TEXT NOT NULL,
                terms_version         TEXT NOT NULL,
                related_reference_id  UUID,
                consent_text_shown    TEXT NOT NULL,
                ip_address            TEXT,
                created_at            TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp()
            );

            CREATE INDEX idx_consent_event_party ON consent_event (party_id);
            CREATE INDEX idx_consent_event_type ON consent_event (consent_type);

            REVOKE UPDATE, DELETE, TRUNCATE ON consent_event FROM ebidhub_app;
            GRANT INSERT, SELECT ON consent_event TO ebidhub_app;
        SQL);
    }

    public function down()
    {
        $this->db->query('GRANT UPDATE, DELETE, TRUNCATE ON consent_event TO ebidhub_app;');
        $this->db->query('DROP TABLE IF EXISTS consent_event CASCADE;');
    }
}
