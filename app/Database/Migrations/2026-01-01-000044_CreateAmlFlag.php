<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-54 / PR-31: Anti-Money Laundering Transaction Monitoring. Flags are
// visible only to SaaS Admin (BR-54's own text: "never to the User or any
// Tenant Admin") — enforced at the controller/route layer (superAdmin
// filter), not by this schema, but noted here since it's the reason this
// table has no tenant_id or any user-facing read path at all.
class CreateAmlFlag extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE aml_pattern_type AS ENUM ('rapid_deposit_release_no_activity', 'shared_external_reference');
            CREATE TYPE aml_flag_status AS ENUM ('open', 'dismissed', 'escalated');

            CREATE TABLE aml_flag (
                id                     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                pattern_type           aml_pattern_type NOT NULL,
                party_id               UUID NOT NULL REFERENCES party(id),
                related_emd_hold_id    UUID REFERENCES emd_hold(id),
                external_reference     TEXT,
                detail                 TEXT NOT NULL,
                status                 aml_flag_status NOT NULL DEFAULT 'open',
                reviewed_by_party_id   UUID REFERENCES party(id),
                review_notes           TEXT,
                str_filed              BOOLEAN NOT NULL DEFAULT false,
                str_reference          TEXT,
                created_at             TIMESTAMPTZ NOT NULL DEFAULT now(),
                reviewed_at            TIMESTAMPTZ
            );

            CREATE INDEX idx_aml_flag_party ON aml_flag (party_id);
            CREATE INDEX idx_aml_flag_status ON aml_flag (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS aml_flag CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS aml_flag_status;');
        $this->db->query('DROP TYPE IF EXISTS aml_pattern_type;');
    }
}
