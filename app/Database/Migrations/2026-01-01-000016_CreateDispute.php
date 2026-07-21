<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDispute extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE dispute_category AS ENUM (
                'payment', 'condition_delivery', 'non_lifting_collection',
                'auction_rejection', 'buyer_non_response'
            );
            -- NOTE: BR-40 also defines a 6th category, Standing Review —
            -- system-initiated (BR-61), not filed by a party. Deliberately
            -- excluded from this enum for now since BR-61 itself is not
            -- built (Tier 4, D-23) — adding it here without the system that
            -- triggers it would be misleading.

            CREATE TYPE dispute_status AS ENUM (
                'filed', 'evidence_window', 'ruled', 'appealed', 'closed'
            );

            CREATE TYPE dispute_ruling_outcome AS ENUM (
                'force_log_noc', 'order_forfeiture', 'rating_consequence', 'dismissed'
            );

            CREATE TABLE dispute (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id            UUID NOT NULL REFERENCES sale_event(id),
                filed_by_party_id         UUID NOT NULL REFERENCES party(id),
                respondent_party_id        UUID NOT NULL REFERENCES party(id),
                category                    dispute_category NOT NULL,
                description                  TEXT NOT NULL,
                status                        dispute_status NOT NULL DEFAULT 'filed',

                evidence_deadline_at          TIMESTAMPTZ,

                ruling_authority_type           TEXT CHECK (ruling_authority_type IN ('tenant_admin', 'super_admin')),
                ruled_by_party_id                 UUID REFERENCES party(id),
                ruling_outcome                     dispute_ruling_outcome,
                ruling_rationale                    TEXT,
                ruled_at                             TIMESTAMPTZ,

                appealed_at                           TIMESTAMPTZ,
                appeal_ruled_by_party_id                UUID REFERENCES party(id),
                appeal_rationale                         TEXT,
                appeal_ruled_at                           TIMESTAMPTZ,

                created_at                                 TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_dispute_sale_event ON dispute (sale_event_id);
            CREATE INDEX idx_dispute_status ON dispute (status);
            CREATE INDEX idx_dispute_filed_by ON dispute (filed_by_party_id);

            CREATE INDEX idx_dispute_filed_by_dismissed ON dispute (filed_by_party_id)
                WHERE ruling_outcome = 'dismissed';

            CREATE TABLE dispute_evidence (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                dispute_id               UUID NOT NULL REFERENCES dispute(id),
                submitted_by_party_id     UUID NOT NULL REFERENCES party(id),
                content                    TEXT NOT NULL,
                created_at                  TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_dispute_evidence_dispute ON dispute_evidence (dispute_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS dispute_evidence CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS dispute CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS dispute_ruling_outcome;');
        $this->db->query('DROP TYPE IF EXISTS dispute_status;');
        $this->db->query('DROP TYPE IF EXISTS dispute_category;');
    }
}
