<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// D-128: TEMPORARY email-based second factor for Super Admin/Custodian
// login, at the project owner's explicit request ("remove TOTP till we
// test it properly, use email instead"). Kept in its own migration,
// same as every other otp_purpose/enum extension on this project (e.g.
// AddEmailOtpPurpose, AddPayoutBankChangeOtpPurpose) — MySQL/Postgres
// both require the new enum value to exist before a later statement in
// the same deploy can use it.
class AddAdminLoginEmailOtpPurpose extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM(
            'registration', 'mpin_reset', 'mpin_reset_email', 'payout_bank_change', 'admin_login_email'
        ) NOT NULL;");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM(
            'registration', 'mpin_reset', 'mpin_reset_email', 'payout_bank_change'
        ) NOT NULL;");
    }
}
