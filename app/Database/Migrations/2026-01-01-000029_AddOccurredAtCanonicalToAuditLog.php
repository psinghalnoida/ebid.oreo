<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOccurredAtCanonicalToAuditLog extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE audit_log ADD COLUMN occurred_at_canonical TEXT;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE audit_log DROP COLUMN occurred_at_canonical;');
    }
}
