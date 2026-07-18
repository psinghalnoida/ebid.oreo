<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateParty extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE EXTENSION IF NOT EXISTS "pgcrypto";

            CREATE TYPE entity_type AS ENUM ('individual', 'organization');
            CREATE TYPE kyc_status AS ENUM ('pending', 'verified', 'suspended');

            CREATE TABLE party (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                mobile_number       VARCHAR(13) NOT NULL UNIQUE,
                mobile_verified_at  TIMESTAMPTZ,
                mpin_hash           TEXT,
                failed_mpin_attempts INTEGER NOT NULL DEFAULT 0,
                entity_type         entity_type NOT NULL DEFAULT 'individual',
                full_name           TEXT,
                pan                 VARCHAR(10),
                aadhaar_masked      VARCHAR(20),
                date_of_birth       DATE,
                occupation          TEXT,
                org_cin             VARCHAR(21),
                org_gstin           VARCHAR(15),
                org_pan             VARCHAR(10),
                org_msme_registration TEXT,
                org_udyam_number    TEXT,
                org_company_type    TEXT,
                org_industry        TEXT,
                org_annual_turnover NUMERIC(15,2),
                org_employee_count  INTEGER,
                kyc_status          kyc_status NOT NULL DEFAULT 'pending',
                kyc_status_reason   TEXT,
                star_rating         NUMERIC(2,1) NOT NULL DEFAULT 3.0
                                       CHECK (star_rating >= 0 AND star_rating <= 5),
                seller_star_rating  NUMERIC(2,1) NOT NULL DEFAULT 3.0
                                       CHECK (seller_star_rating >= 0 AND seller_star_rating <= 5),
                created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                archived_at         TIMESTAMPTZ
            );

            CREATE INDEX idx_party_mobile ON party (mobile_number) WHERE archived_at IS NULL;
            CREATE INDEX idx_party_kyc_status ON party (kyc_status) WHERE archived_at IS NULL;
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS kyc_status;');
        $this->db->query('DROP TYPE IF EXISTS entity_type;');
    }
}
