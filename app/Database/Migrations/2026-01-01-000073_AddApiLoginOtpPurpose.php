<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Bugfix: UserAuthApiService's REST/JWT login flow (requestLoginOtp /
// verifyLoginOtp) has always written OTPs with purpose = 'api_login'
// (see AuthService::requestOtp's allowed-purpose list), but the
// otp_verification.purpose ENUM never had 'api_login' added to it. MySQL
// silently stores an out-of-list ENUM value as an empty string instead of
// erroring, so every api_login OTP was saved with purpose = '' — which
// meant verifyOtp()'s `WHERE purpose = 'api_login'` lookup could never
// find it, and every login OTP verification failed with "Incorrect or
// expired OTP" no matter how correct/fresh the OTP actually was.
//
// Same enum-widening pattern as AddEmailOtpPurpose, AddPayoutBankChangeOtpPurpose,
// and AddAdminLoginEmailOtpPurpose before it — kept in its own migration
// since MySQL/Postgres both require the new enum value to exist before a
// later statement in the same deploy can use it.
class AddApiLoginOtpPurpose extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM(
            'registration', 'mpin_reset', 'mpin_reset_email', 'payout_bank_change', 'admin_login_email', 'api_login'
        ) NOT NULL;");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE otp_verification MODIFY COLUMN purpose ENUM(
            'registration', 'mpin_reset', 'mpin_reset_email', 'payout_bank_change', 'admin_login_email'
        ) NOT NULL;");
    }
}
