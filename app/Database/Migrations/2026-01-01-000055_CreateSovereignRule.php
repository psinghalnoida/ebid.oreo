<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// PR-04/BR-01/BR-04: Sovereign Rule Revision — the Super Admin's own
// "Rules & Specifications" module. rule_key is set only for rules this
// codebase actually wires into live behavior (e.g. 'BR-43.bid_ceiling');
// a NULL rule_key is a freeform governance rule the Super Admin defined
// (Title/Statement/Logic + audit trail per BR-01) with no live code
// effect — there is no generic rule-expression evaluator in this
// codebase, and building one would be a rules-engine rewrite, out of
// scope for this pass.
class CreateSovereignRule extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE sovereign_rule (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                rule_key                VARCHAR(120) UNIQUE,
                title                   TEXT NOT NULL,
                statement               TEXT NOT NULL,
                logic                   TEXT NOT NULL,
                numeric_value           NUMERIC(18,4),
                version                 INTEGER NOT NULL DEFAULT 1,
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            -- PR-04 step 5: "System versions the change, commits it" — a
            -- full snapshot per revision, distinct from (and in addition
            -- to) the tamper-evident audit_log hash chain (BR-05), since
            -- PR-04 asks for versioning as its own explicit capability
            -- (e.g. viewing prior wording of a rule), not just a log line.
            CREATE TABLE sovereign_rule_revision (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                rule_id                 UUID NOT NULL REFERENCES sovereign_rule(id),
                version                 INTEGER NOT NULL,
                title                   TEXT NOT NULL,
                statement               TEXT NOT NULL,
                logic                   TEXT NOT NULL,
                numeric_value           NUMERIC(18,4),
                reason_for_modification TEXT NOT NULL,
                changed_by_party_id     UUID REFERENCES party(id),
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_sovereign_rule_revision_rule ON sovereign_rule_revision (rule_id, version DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS sovereign_rule_revision CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS sovereign_rule CASCADE;');
    }
}
