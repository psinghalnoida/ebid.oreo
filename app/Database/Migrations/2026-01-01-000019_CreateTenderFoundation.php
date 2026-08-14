<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateTenderFoundation extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE tender_interest (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                registered_at            DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                UNIQUE (sale_event_id, party_id),

                CONSTRAINT fk_tender_interest_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_interest_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );
            CREATE TABLE tender_eligibility (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                source                   ENUM('interest', 'direct') NOT NULL,

                approved_by_party_id CHAR(36) NOT NULL,

                approved_at                 DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                UNIQUE (sale_event_id, party_id),

                CONSTRAINT fk_tender_eligibility_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_eligibility_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_tender_eligibility_approved_by_party_id FOREIGN KEY (approved_by_party_id) REFERENCES party(id)
            );
            CREATE TABLE tender_document (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                uploaded_by_party_id CHAR(36) NOT NULL,

                document_type             ENUM('terms_of_sale', 'required_document', 'emd_information') NOT NULL,

                title                       TEXT NOT NULL,

                file_path                    TEXT,

                description_text                TEXT,

                created_at                        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_tender_document_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_document_uploaded_by_party_id FOREIGN KEY (uploaded_by_party_id) REFERENCES party(id)
            );

            CREATE TABLE tender_stakeholder_token (

                id                  CHAR(36) PRIMARY KEY,

                sale_event_id CHAR(36) NOT NULL,

                token                   VARCHAR(255) NOT NULL UNIQUE,

                label                     TEXT,

                created_by_party_id CHAR(36) NOT NULL,

                created_at                    DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                revoked_at                      DATETIME(6),

                CONSTRAINT fk_tender_stakeholder_token_sale_event_id FOREIGN KEY (sale_event_id) REFERENCES sale_event(id),

                CONSTRAINT fk_tender_stakeholder_token_created_by_party_id FOREIGN KEY (created_by_party_id) REFERENCES party(id)
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
        $this->db->query('DROP TABLE IF EXISTS tender_eligibility CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS tender_interest CASCADE;');
    }
}
