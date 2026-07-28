<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Phase 3D: backup TOTP codes — 10 one-time codes generated at
// enrollment/re-enrollment, hashed at rest (never stored plain), each
// usable once if the authenticator device is unavailable.
class CreateSuperAdminBackupCode extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE super_admin_backup_code (
                id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id    UUID NOT NULL REFERENCES party(id),
                code_hash   TEXT NOT NULL,
                used_at     TIMESTAMPTZ,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX idx_backup_code_party ON super_admin_backup_code (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS super_admin_backup_code CASCADE;');
    }
}
