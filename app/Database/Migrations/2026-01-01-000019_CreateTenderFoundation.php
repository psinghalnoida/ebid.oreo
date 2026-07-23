<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTenderFoundation extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE tender_interest (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                party_id               UUID NOT NULL REFERENCES party(id),
                registered_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (sale_event_id, party_id)
            );

            CREATE TYPE tender_eligibility_source AS ENUM ('interest', 'direct');

            CREATE TABLE tender_eligibility (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                party_id               UUID NOT NULL REFERENCES party(id),
                source                   tender_eligibility_source NOT NULL,
                approved_by_party_id      UUID NOT NULL REFERENCES party(id),
                approved_at                 TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE (sale_event_id, party_id)
            );

            CREATE TYPE tender_document_type AS ENUM ('terms_of_sale', 'required_document', 'emd_information');

            CREATE TABLE tender_document (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                uploaded_by_party_id    UUID NOT NULL REFERENCES party(id),
                document_type             tender_document_type NOT NULL,
                title                       TEXT NOT NULL,
                file_path                    TEXT,
                description_text                TEXT,
                created_at                        TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE TABLE tender_stakeholder_token (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                sale_event_id        UUID NOT NULL REFERENCES sale_event(id),
                token                   TEXT NOT NULL UNIQUE,
                label                     TEXT,
                created_by_party_id         UUID NOT NULL REFERENCES party(id),
                created_at                    TIMESTAMPTZ NOT NULL DEFAULT now(),
                revoked_at                      TIMESTAMPTZ
            );

            CREATE INDEX idx_tender_interest_sale_event ON tender_interest (sale_event_id);
            CREATE INDEX idx_tender_eligibility_sale_event ON tender_eligibility (sale_event_id);
            CREATE INDEX idx_tender_document_sale_event ON tender_document (sale_event_id);
            CREATE INDEX idx_tender_stakeholder_token_lookup ON tender_stakeholder_token (token);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS tender_stakeholder_token CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS tender_document CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS tender_document_type;');
        $this->db->query('DROP TABLE IF EXISTS tender_eligibility CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS tender_eligibility_source;');
        $this->db->query('DROP TABLE IF EXISTS tender_interest CASCADE;');
    }
}
