<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-18: "Every patron maintains up to four address records:
// Registered, Billing, Correspondence, and Site/Yard" — one row per
// type per party (UNIQUE constraint), not an open-ended list.
class CreatePartyAddress extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE party_address (

                id              CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                address_type    ENUM('registered', 'billing', 'correspondence', 'site_yard') NOT NULL,

                line1           TEXT NOT NULL,

                line2           TEXT,

                city            TEXT NOT NULL,

                district        TEXT NOT NULL,

                state           TEXT NOT NULL,

                country         VARCHAR(255) NOT NULL DEFAULT 'India',

                pin_code        VARCHAR(6) NOT NULL,

                gps_lat         NUMERIC(9,6),

                gps_lng         NUMERIC(9,6),

                created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                UNIQUE (party_id, address_type),

                CONSTRAINT fk_party_address_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_party_address_party ON party_address (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_address CASCADE;');
    }
}
