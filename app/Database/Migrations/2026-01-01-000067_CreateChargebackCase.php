<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-52/PR-30: Chargeback Handling & Representment. Two independent
// tracks on one case, per PR-30 step 193's explicit "independent of the
// representment outcome" framing:
//   1. status: filed -> represented (evidence auto-assembled) ->
//      resolved_won/resolved_lost (SaaS Admin records the payment
//      gateway's eventual decision -- honest gap, same as every other
//      real-gateway-dependent step in this app: the evidence assembly
//      and case tracking are real, submitting to an actual card network
//      and receiving its verdict requires the gateway integration that's
//      already an accepted, tracked external dependency).
//   2. against_approved_forfeiture + the integrity_* columns: BR-52's
//      "distinct account-integrity event" review, only relevant when the
//      chargeback targets an already-approved, legitimate forfeiture.
class CreateChargebackCase extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE chargeback_case (

                id                                    CHAR(36) PRIMARY KEY,

                emd_hold_id CHAR(36) NOT NULL,

                sale_event_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                amount                                NUMERIC(14,2) NOT NULL,

                filed_reason                          TEXT NOT NULL,

                against_approved_forfeiture           BOOLEAN NOT NULL DEFAULT false,

                evidence_package                      JSON NOT NULL,

                status                                ENUM('filed', 'represented', 'resolved_won', 'resolved_lost') NOT NULL DEFAULT 'filed',

                filed_at                              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                evidence_assembled_at                 DATETIME(6),

                representment_outcome_by_party_id CHAR(36),

                representment_notes                   TEXT,

                representment_resolved_at             DATETIME(6),

                integrity_reviewed_by_party_id CHAR(36),

                integrity_review_notes                TEXT,

                integrity_rating_consequence_applied  BOOLEAN,

                integrity_reviewed_at                 DATETIME(6),

                CONSTRAINT fk_chargeback_case_emd_hold_id FOREIGN KEY (emd_hold_id) REFERENCES emd_hold(id),

                CONSTRAINT fk_chargeback_case_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_chargeback_case_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_chargeback_case_representment_outcome_by_party_id FOREIGN KEY (representment_outcome_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_chargeback_case_integrity_reviewed_by_party_id FOREIGN KEY (integrity_reviewed_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_chargeback_case_party ON chargeback_case (party_id);
            CREATE INDEX idx_chargeback_case_status ON chargeback_case (status);
            CREATE INDEX idx_chargeback_case_integrity ON chargeback_case (against_approved_forfeiture, integrity_reviewed_at);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS chargeback_case CASCADE;');
    }
}
