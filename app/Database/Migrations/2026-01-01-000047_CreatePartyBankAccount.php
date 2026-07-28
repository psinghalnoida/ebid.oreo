<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-50/PR-28: registered payout banking details did not exist anywhere in
// this schema before now — "payout" was purely an EMD status/ledger event
// (emd_hold.status/released_at) with no destination-account concept at
// all, confirmed by reading every fund-release code path before building
// this. Only one row per party is ever 'cooling_off' or 'active' at a
// time — a new change immediately supersedes whichever row was current.
class CreatePartyBankAccount extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE bank_account_status AS ENUM ('cooling_off', 'active', 'superseded');

            CREATE TABLE party_bank_account (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id                UUID NOT NULL REFERENCES party(id),
                account_holder_name     TEXT NOT NULL,
                account_number          TEXT NOT NULL,
                ifsc_code               TEXT NOT NULL,
                status                  bank_account_status NOT NULL DEFAULT 'cooling_off',
                activates_at            TIMESTAMPTZ NOT NULL,
                initiated_by_party_id   UUID NOT NULL REFERENCES party(id),
                created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_party_bank_account_party ON party_bank_account (party_id, created_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS party_bank_account CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS bank_account_status;');
    }
}
