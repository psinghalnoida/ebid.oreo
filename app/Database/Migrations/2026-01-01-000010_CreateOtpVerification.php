<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOtpVerification extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE otp_purpose AS ENUM ('registration', 'mpin_reset');

            CREATE TABLE otp_verification (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                mobile_number       VARCHAR(13) NOT NULL,
                otp_hash            TEXT NOT NULL,
                purpose             otp_purpose NOT NULL,
                attempts            INTEGER NOT NULL DEFAULT 0,
                expires_at          TIMESTAMPTZ NOT NULL,
                verified_at         TIMESTAMPTZ,
                created_at          TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_otp_mobile_purpose ON otp_verification (mobile_number, purpose, created_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS otp_verification CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS otp_purpose;');
    }
}
