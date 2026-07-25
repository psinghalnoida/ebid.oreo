<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeAuditLogPayloadToText extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE audit_log ALTER COLUMN payload TYPE TEXT;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE audit_log ALTER COLUMN payload TYPE JSONB USING payload::jsonb;');
    }
}
