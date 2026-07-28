<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50: a settlement whose fund release is withheld by the payout-account
// gate (still in its 24h cooling-off window, or a high-value payout
// pending Tenant/SaaS Admin review) is genuinely NOT complete yet, but is
// also distinct from 'stalled' (BR-39's meaning: sat incomplete on the
// buyer/seller NOC+rating steps for too long — this is the opposite case,
// where all four of THOSE steps are done and only the fund release itself
// is on hold).
class AddPayoutHeldSettlementStatus extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TYPE settlement_status ADD VALUE IF NOT EXISTS 'payout_held';");
    }

    public function down()
    {
        // Postgres does not support removing an enum value directly.
        // No-op — additive-only, harmless to leave in place if unused.
    }
}
