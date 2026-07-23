<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenderReview extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE tender_review_status AS ENUM ('provisional', 'extension_granted', 'rejected', 'confirmed');

            CREATE TABLE tender_review (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                bid_id                  UUID NOT NULL REFERENCES bid(id),
                party_id                  UUID NOT NULL REFERENCES party(id),
                round_number                INTEGER NOT NULL DEFAULT 1,
                status                        tender_review_status NOT NULL DEFAULT 'provisional',

                extension_reason                TEXT,
                extension_granted_by_party_id      UUID REFERENCES party(id),
                extension_granted_at                  TIMESTAMPTZ,

                rejection_reason                        TEXT,
                rejected_by_party_id                       UUID REFERENCES party(id),
                rejected_at                                   TIMESTAMPTZ,

                confirmed_by_party_id                           UUID REFERENCES party(id),
                confirmed_at                                       TIMESTAMPTZ,

                created_at                                           TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_tender_review_sale_event ON tender_review (sale_event_id);
            CREATE INDEX idx_tender_review_status ON tender_review (sale_event_id, status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tender_review CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS tender_review_status;');
    }
}
