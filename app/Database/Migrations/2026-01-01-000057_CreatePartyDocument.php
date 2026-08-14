<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-17/PR-15: "Patron uploads required documents to the secure document
// vault. System encrypts documents..." — the closed list of 8 document
// types named in BR-17's text. Files are encrypted at rest (real AES-256
// via CI4's Encryption service, KycService::encryptAndStore) and stored
// under writable/kyc_vault/, outside the public webroot — unlike listing
// photos, a KYC document must never be reachable by a guessed URL.
class CreatePartyDocument extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE party_document (

                id                  CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                document_type       ENUM(
                'pan_card', 'gst_certificate', 'aadhaar_card', 'certificate_of_incorporation',
                'board_resolution', 'power_of_attorney', 'cancelled_cheque', 'msme_certificate'
            ) NOT NULL,

                encrypted_path      TEXT NOT NULL,

                original_filename   TEXT NOT NULL,

                mime_type           TEXT NOT NULL,

                uploaded_at         DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_party_document_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_party_document_party ON party_document (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_document CASCADE;');
    }
}
