<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
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
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE sovereign_rule (

                id                      CHAR(36) PRIMARY KEY,

                rule_key                VARCHAR(120) UNIQUE,

                title                   TEXT NOT NULL,

                statement               TEXT NOT NULL,

                logic                   TEXT NOT NULL,

                numeric_value           NUMERIC(18,4),

                version                 INTEGER NOT NULL DEFAULT 1,

                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
            );

            -- PR-04 step 5: "System versions the change, commits it" — a
            -- full snapshot per revision, distinct from (and in addition
            -- to) the tamper-evident audit_log hash chain (BR-05), since
            -- PR-04 asks for versioning as its own explicit capability
            -- (e.g. viewing prior wording of a rule), not just a log line.
            CREATE TABLE sovereign_rule_revision (

                id                      CHAR(36) PRIMARY KEY,

                rule_id CHAR(36) NOT NULL,

                version                 INTEGER NOT NULL,

                title                   TEXT NOT NULL,

                statement               TEXT NOT NULL,

                logic                   TEXT NOT NULL,

                numeric_value           NUMERIC(18,4),

                reason_for_modification TEXT NOT NULL,

                changed_by_party_id CHAR(36),

                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_sovereign_rule_revision_rule_id FOREIGN KEY (rule_id) REFERENCES sovereign_rule(id),

                CONSTRAINT fk_sovereign_rule_revision_changed_by_party_id FOREIGN KEY (changed_by_party_id) REFERENCES party(id)
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
