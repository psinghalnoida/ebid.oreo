<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Custodian login redesign: mobile_number + mPIN becomes the DEFAULT
// Custodian login method, with Google Authenticator (TOTP, already on
// `party`) as an equal alternative — see SuperAdminAuthService. This is a
// SEPARATE mPIN from the shared `party.mpin_hash` used by every other
// role, deliberately: a party that also holds a bidder/seller role keeps
// an independent admin credential, so compromising (or resetting) one
// mPIN never touches the other. Lives on `super_admin_credential`
// (same table as the legacy email+password credential, which stays in
// place unused by the login UI) rather than `party`, for the same
// separation-of-concerns reason that table itself was split out.
class AddMpinToSuperAdminCredential extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE super_admin_credential ADD COLUMN mpin_hash TEXT NULL;');
        $this->db->query('ALTER TABLE super_admin_credential ADD COLUMN failed_mpin_attempts INTEGER NOT NULL DEFAULT 0;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE super_admin_credential DROP COLUMN mpin_hash;');
        $this->db->query('ALTER TABLE super_admin_credential DROP COLUMN failed_mpin_attempts;');
    }
}
