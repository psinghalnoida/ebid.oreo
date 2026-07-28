<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-35: the full graduated rating event table. `event_key` makes a
// named event (e.g. 'default_1st', 'frivolous_dispute') a real,
// queryable identifier on the row — not just free text buried in
// `reason` — so a pattern count (e.g. "how many defaults in 12 months")
// can be derived reliably. The clean-transaction-streak columns are a
// GENERAL positive-reinforcement counter distinct from BR-38's
// crawl_back_clean_completed_* (which only counts during active
// rehabilitation) — every party accrues this regardless of Crawl-Back
// status.
class AddBR35RatingInfrastructure extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE rating_event ADD COLUMN event_key TEXT;
            CREATE INDEX idx_rating_event_key ON rating_event (party_id, rating_role, event_key, created_at);

            ALTER TABLE party
                ADD COLUMN clean_transaction_streak_buyer INT NOT NULL DEFAULT 0,
                ADD COLUMN clean_transaction_streak_seller INT NOT NULL DEFAULT 0;
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party
                DROP COLUMN IF EXISTS clean_transaction_streak_buyer,
                DROP COLUMN IF EXISTS clean_transaction_streak_seller;
            DROP INDEX IF EXISTS idx_rating_event_key;
            ALTER TABLE rating_event DROP COLUMN IF EXISTS event_key;
        SQL);
    }
}
