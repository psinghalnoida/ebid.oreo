<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50/PR-28: "System triggers OTP re-verification of the account holder
// before accepting the change request" — reuses the existing OTP
// infrastructure (AuthService/OtpVerificationModel), same precedent as
// AddEmailOtpPurpose's 'mpin_reset_email' addition.
class AddBankAccountChangeOtpPurpose extends Migration
{
    public function up()
    {
        // Postgres requires ALTER TYPE ... ADD VALUE to run outside an
        // explicit transaction block in older versions; works fine on
        // modern Postgres (16, used throughout this project's testing).
        $this->db->query("ALTER TYPE otp_purpose ADD VALUE IF NOT EXISTS 'bank_account_change';");
    }

    public function down()
    {
        // Postgres does not support removing an enum value directly.
        // No-op — additive-only, harmless to leave in place if unused.
    }
}
