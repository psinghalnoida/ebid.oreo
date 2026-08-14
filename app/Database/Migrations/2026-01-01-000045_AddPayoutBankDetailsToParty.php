<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-50: Payout Account Change Control. Adds a single bank-details field
// per party (used for buyer EMD refunds today; the same field serves a
// seller's settlement payout too, since a Seller is just a Buyer upgraded
// on a tenant, BR-09 — one party, one bank record). The *_pending_*
// columns implement the mandatory 24-hour cooling-off: a requested change
// sits here, not in the active columns, until it activates.
class AddPayoutBankDetailsToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                ADD COLUMN payout_bank_account_number TEXT,
                ADD COLUMN payout_bank_ifsc TEXT,
                ADD COLUMN payout_bank_updated_at DATETIME(6),
                ADD COLUMN payout_bank_pending_account_number TEXT,
                ADD COLUMN payout_bank_pending_ifsc TEXT,
                ADD COLUMN payout_bank_pending_activates_at DATETIME(6);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                DROP COLUMN payout_bank_account_number,
                DROP COLUMN payout_bank_ifsc,
                DROP COLUMN payout_bank_updated_at,
                DROP COLUMN payout_bank_pending_account_number,
                DROP COLUMN payout_bank_pending_ifsc,
                DROP COLUMN payout_bank_pending_activates_at;
        SQL);
    }
}
