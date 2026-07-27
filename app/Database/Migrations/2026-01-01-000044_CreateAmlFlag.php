<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-54/PR-31: Anti-Money Laundering Transaction Monitoring — the three
// specific patterns named in the governing text, not the larger scoring/
// case-management platform discussed and explicitly deferred (see
// docs/DECISIONS.md).
class CreateAmlFlag extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE aml_flag_type AS ENUM ('deposit_refund_cycle', 'kyc_inconsistent_deposit', 'shared_funding_source');
            CREATE TYPE aml_flag_status AS ENUM ('open', 'reviewed_no_action', 'reviewed_str_filed');

            CREATE TABLE aml_flag (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                flag_type               aml_flag_type NOT NULL,
                party_id                UUID NOT NULL REFERENCES party(id),
                detail                  TEXT NOT NULL,
                status                  aml_flag_status NOT NULL DEFAULT 'open',
                reviewed_by_party_id    UUID REFERENCES party(id),
                review_notes            TEXT,
                str_reference           TEXT,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
                reviewed_at             TIMESTAMPTZ
            );

            CREATE INDEX idx_aml_flag_party ON aml_flag (party_id);
            CREATE INDEX idx_aml_flag_status ON aml_flag (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS aml_flag CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS aml_flag_status;');
        $this->db->query('DROP TYPE IF EXISTS aml_flag_type;');
    }
}
