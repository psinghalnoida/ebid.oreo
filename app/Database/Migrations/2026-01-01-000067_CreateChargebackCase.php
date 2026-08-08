<?php

namespace App\Database\Migrations;

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
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE chargeback_status AS ENUM ('filed', 'represented', 'resolved_won', 'resolved_lost');

            CREATE TABLE chargeback_case (
                id                                    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                emd_hold_id                           UUID NOT NULL REFERENCES emd_hold(id),
                sale_event_id                         UUID NOT NULL REFERENCES sale_event(id),
                party_id                              UUID NOT NULL REFERENCES party(id),
                amount                                NUMERIC(14,2) NOT NULL,
                filed_reason                          TEXT NOT NULL,
                against_approved_forfeiture           BOOLEAN NOT NULL DEFAULT false,
                evidence_package                      JSONB NOT NULL,
                status                                chargeback_status NOT NULL DEFAULT 'filed',
                filed_at                              TIMESTAMPTZ NOT NULL DEFAULT now(),
                evidence_assembled_at                 TIMESTAMPTZ,
                representment_outcome_by_party_id     UUID REFERENCES party(id),
                representment_notes                   TEXT,
                representment_resolved_at             TIMESTAMPTZ,
                integrity_reviewed_by_party_id        UUID REFERENCES party(id),
                integrity_review_notes                TEXT,
                integrity_rating_consequence_applied  BOOLEAN,
                integrity_reviewed_at                 TIMESTAMPTZ
            );

            CREATE INDEX idx_chargeback_case_party ON chargeback_case (party_id);
            CREATE INDEX idx_chargeback_case_status ON chargeback_case (status);
            CREATE INDEX idx_chargeback_case_integrity ON chargeback_case (against_approved_forfeiture, integrity_reviewed_at);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS chargeback_case CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS chargeback_status;');
    }
}
