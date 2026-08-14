<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE party (
                id                  CHAR(36) PRIMARY KEY,
                mobile_number       VARCHAR(13) NOT NULL UNIQUE,
                mobile_verified_at  DATETIME(6),
                mpin_hash           TEXT,
                failed_mpin_attempts INTEGER NOT NULL DEFAULT 0,
                entity_type         ENUM('individual', 'organization') NOT NULL DEFAULT 'individual',
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
                kyc_status          ENUM('pending', 'verified', 'suspended') NOT NULL DEFAULT 'pending',
                kyc_status_reason   TEXT,
                star_rating         NUMERIC(2,1) NOT NULL DEFAULT 3.0
                                       CHECK (star_rating >= 0 AND star_rating <= 5),
                seller_star_rating  NUMERIC(2,1) NOT NULL DEFAULT 3.0
                                       CHECK (seller_star_rating >= 0 AND seller_star_rating <= 5),
                created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                archived_at         DATETIME(6)
            );

            -- Partial indexes (WHERE archived_at IS NULL) have no MySQL
            -- equivalent; both are plain (non-unique) indexes so dropping the
            -- WHERE is a pure performance trade-off (a few archived rows stay
            -- indexed too), not a correctness change -- unlike the
            -- partial *unique* indexes elsewhere in this schema.
            CREATE INDEX idx_party_mobile ON party (mobile_number);
            CREATE INDEX idx_party_kyc_status ON party (kyc_status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party CASCADE;');
    }
}
