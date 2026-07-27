<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Phase 3A: account deletion request — soft delete with a 30-day grace
// period and a cancellation option, reusing the same
// "stage-then-scheduler-finalizes" pattern as BR-50's payout bank
// cooling-off, rather than archiving immediately.
class AddAccountDeletionRequestToParty extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party
                ADD COLUMN deletion_requested_at TIMESTAMPTZ,
                ADD COLUMN deletion_reason TEXT,
                ADD COLUMN last_login_at TIMESTAMPTZ;
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party
                DROP COLUMN IF EXISTS deletion_requested_at,
                DROP COLUMN IF EXISTS deletion_reason,
                DROP COLUMN IF EXISTS last_login_at;
        SQL);
    }
}
