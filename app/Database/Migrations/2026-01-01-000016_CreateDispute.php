<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateDispute extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            -- NOTE: BR-40 also defines a 6th category, Standing Review —
            -- system-initiated (BR-61), not filed by a party. Deliberately
            -- excluded from this enum for now since BR-61 itself is not
            -- built (Tier 4, D-23) — adding it here without the system that
            -- triggers it would be misleading.
            CREATE TABLE dispute (

                id                      CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                filed_by_party_id CHAR(36) NOT NULL,

                respondent_party_id CHAR(36) NOT NULL,

                category                    ENUM(
                'payment', 'condition_delivery', 'non_lifting_collection',
                'auction_rejection', 'buyer_non_response'
            ) NOT NULL,

                description                  TEXT NOT NULL,

                status                        ENUM(
                'filed', 'evidence_window', 'ruled', 'appealed', 'closed'
            ) NOT NULL DEFAULT 'filed',


                evidence_deadline_at          DATETIME(6),


                ruling_authority_type           TEXT CHECK (ruling_authority_type IN ('tenant_admin', 'super_admin')),

                ruled_by_party_id CHAR(36),

                ruling_outcome                     ENUM(
                'force_log_noc', 'order_forfeiture', 'rating_consequence', 'dismissed'
            ),

                ruling_rationale                    TEXT,

                ruled_at                             DATETIME(6),


                appealed_at                           DATETIME(6),

                appeal_ruled_by_party_id CHAR(36),

                appeal_rationale                         TEXT,

                appeal_ruled_at                           DATETIME(6),


                created_at                                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_dispute_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_dispute_filed_by_party_id FOREIGN KEY (filed_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_dispute_respondent_party_id FOREIGN KEY (respondent_party_id) REFERENCES party(id),

                CONSTRAINT fk_dispute_ruled_by_party_id FOREIGN KEY (ruled_by_party_id) REFERENCES party(id),

                CONSTRAINT fk_dispute_appeal_ruled_by_party_id FOREIGN KEY (appeal_ruled_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_dispute_sale_event ON dispute (sale_event_id);
            CREATE INDEX idx_dispute_status ON dispute (status);
            CREATE INDEX idx_dispute_filed_by ON dispute (filed_by_party_id);

            CREATE INDEX idx_dispute_filed_by_dismissed ON dispute (filed_by_party_id);

            CREATE TABLE dispute_evidence (

                id                      CHAR(36) PRIMARY KEY,

                dispute_id CHAR(36) NOT NULL,

                submitted_by_party_id CHAR(36) NOT NULL,

                content                    TEXT NOT NULL,

                created_at                  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_dispute_evidence_dispute_id FOREIGN KEY (dispute_id) REFERENCES dispute(id),

                CONSTRAINT fk_dispute_evidence_submitted_by_party_id FOREIGN KEY (submitted_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_dispute_evidence_dispute ON dispute_evidence (dispute_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS dispute_evidence CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS dispute CASCADE;');
    }
}
