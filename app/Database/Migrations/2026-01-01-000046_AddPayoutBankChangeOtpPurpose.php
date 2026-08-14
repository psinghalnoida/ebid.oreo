<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50: OTP re-verification of the account holder is mandatory before a
// payout bank-detail change is even accepted for the 24-hour cooling-off.
// Kept in its own migration, same as every other otp_purpose/enum
// extension on this project (e.g. AddEmailOtpPurpose, AddStandingReviewToDispute)
// — Postgres does not allow a new enum value to be used in the same
// transaction it was added in.
class AddPayoutBankChangeOtpPurpose extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM(
            'registration', 'mpin_reset', 'mpin_reset_email', 'payout_bank_change'
        ) NOT NULL;");
    }

    public function down()
    {
    }
}
