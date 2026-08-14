<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateOtpVerification extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE otp_verification (
                id                  CHAR(36) PRIMARY KEY,
                mobile_number       VARCHAR(13) NOT NULL,
                otp_hash            TEXT NOT NULL,
                purpose             ENUM('registration', 'mpin_reset') NOT NULL,
                attempts            INTEGER NOT NULL DEFAULT 0,
                expires_at          DATETIME(6) NOT NULL,
                verified_at         DATETIME(6),
                created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
            );

            CREATE INDEX idx_otp_mobile_purpose ON otp_verification (mobile_number, purpose, created_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS otp_verification CASCADE;');
    }
}
