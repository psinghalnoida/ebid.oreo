<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WidenOtpIdentifierColumn extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE otp_verification MODIFY COLUMN mobile_number VARCHAR(255) NOT NULL;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE otp_verification MODIFY COLUMN mobile_number VARCHAR(13) NOT NULL;');
    }
}
