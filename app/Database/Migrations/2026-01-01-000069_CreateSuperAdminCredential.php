<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Custodian (Super Admin) login now authenticates with EMAIL + PASSWORD
// instead of mobile_number + mPIN (see SuperAdminAuthService/
// SuperAdminAuthApiController). Deliberately a SEPARATE table from
// `party` rather than adding email/password columns onto it:
// - `party` remains the one identity record shared by every role
//   (buyer/seller/... /tenant_admin), keyed on mobile_number + mPIN —
//   that model is unchanged and untouched here.
// - A Custodian is still a `party` row (still gets a `party_role` row
//   with role = 'super_admin', still goes through TOTP/email-OTP 2FA
//   exactly as before) — this table only adds the alternate first-factor
//   credential (email + bcrypt password hash) specific to that one role,
//   without perturbing the shared party schema or any other role's login.
class CreateSuperAdminCredential extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE super_admin_credential (
                id              CHAR(36) PRIMARY KEY,
                party_id        CHAR(36) NOT NULL UNIQUE,
                email           VARCHAR(255) NOT NULL UNIQUE,
                password_hash   TEXT NOT NULL,
                created_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at      DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_super_admin_credential_party_id
                    FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_super_admin_credential_email ON super_admin_credential (email);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS super_admin_credential CASCADE;');
    }
}
