<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Phase 3A: account deletion request — soft delete with a 30-day grace
// period and a cancellation option, reusing the same
// "stage-then-scheduler-finalizes" pattern as BR-50's payout bank
// cooling-off, rather than archiving immediately.
class AddAccountDeletionRequestToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                ADD COLUMN deletion_requested_at DATETIME(6),
                ADD COLUMN deletion_reason TEXT,
                ADD COLUMN last_login_at DATETIME(6);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                DROP COLUMN deletion_requested_at,
                DROP COLUMN deletion_reason,
                DROP COLUMN last_login_at;
        SQL);
    }
}
