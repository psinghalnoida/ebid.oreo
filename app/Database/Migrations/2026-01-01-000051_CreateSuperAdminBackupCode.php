<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Phase 3D: backup TOTP codes — 10 one-time codes generated at
// enrollment/re-enrollment, hashed at rest (never stored plain), each
// usable once if the authenticator device is unavailable.
class CreateSuperAdminBackupCode extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE super_admin_backup_code (

                id          CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                code_hash   TEXT NOT NULL,

                used_at     DATETIME(6),

                created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_super_admin_backup_code_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );
            CREATE INDEX idx_backup_code_party ON super_admin_backup_code (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS super_admin_backup_code CASCADE;');
    }
}
