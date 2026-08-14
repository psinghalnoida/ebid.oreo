<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ChangeAuditLogPayloadToText extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE audit_log MODIFY COLUMN payload TEXT NOT NULL;');
    }

    public function down()
    {
        // Best-effort rollback, same honest limitation as the original
        // Postgres ::jsonb cast: fails if any row's payload is not valid
        // JSON. Not expected to be exercised in practice.
        $this->db->query('ALTER TABLE audit_log MODIFY COLUMN payload JSON NOT NULL;');
    }
}
