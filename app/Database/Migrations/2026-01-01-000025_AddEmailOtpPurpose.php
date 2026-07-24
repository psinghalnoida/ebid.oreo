<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailOtpPurpose extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TYPE otp_purpose ADD VALUE IF NOT EXISTS 'mpin_reset_email';");
    }

    public function down()
    {
    }
}
