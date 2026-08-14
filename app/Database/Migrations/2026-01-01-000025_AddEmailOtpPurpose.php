<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailOtpPurpose extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM('registration', 'mpin_reset', 'mpin_reset_email') NOT NULL;");
    }

    public function down()
    {
    }
}
