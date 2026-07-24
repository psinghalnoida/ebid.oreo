<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WidenOtpIdentifierColumn extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE otp_verification ALTER COLUMN mobile_number TYPE VARCHAR(255);');
    }

    public function down()
    {
        $this->db->query("ALTER TABLE otp_verification ALTER COLUMN mobile_number TYPE VARCHAR(13);");
    }
}
