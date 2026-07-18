<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRatingStateToParty extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party ADD COLUMN offence_count_buyer INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN offence_count_seller INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN crawl_back_active_buyer BOOLEAN NOT NULL DEFAULT false;
            ALTER TABLE party ADD COLUMN crawl_back_clean_required_buyer INTEGER;
            ALTER TABLE party ADD COLUMN crawl_back_clean_completed_buyer INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN crawl_back_active_seller BOOLEAN NOT NULL DEFAULT false;
            ALTER TABLE party ADD COLUMN crawl_back_clean_required_seller INTEGER;
            ALTER TABLE party ADD COLUMN crawl_back_clean_completed_seller INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN shadow_banned_at_buyer TIMESTAMPTZ;
            ALTER TABLE party ADD COLUMN shadow_banned_at_seller TIMESTAMPTZ;

            ALTER TABLE party ADD COLUMN deposit_override_amount NUMERIC(14,2);

            ALTER TABLE party ADD COLUMN forced_neutral_count_buyer INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN forced_neutral_count_seller INTEGER NOT NULL DEFAULT 0;
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE party
                DROP COLUMN IF EXISTS offence_count_buyer,
                DROP COLUMN IF EXISTS offence_count_seller,
                DROP COLUMN IF EXISTS crawl_back_active_buyer,
                DROP COLUMN IF EXISTS crawl_back_clean_required_buyer,
                DROP COLUMN IF EXISTS crawl_back_clean_completed_buyer,
                DROP COLUMN IF EXISTS crawl_back_active_seller,
                DROP COLUMN IF EXISTS crawl_back_clean_required_seller,
                DROP COLUMN IF EXISTS crawl_back_clean_completed_seller,
                DROP COLUMN IF EXISTS shadow_banned_at_buyer,
                DROP COLUMN IF EXISTS shadow_banned_at_seller,
                DROP COLUMN IF EXISTS deposit_override_amount,
                DROP COLUMN IF EXISTS forced_neutral_count_buyer,
                DROP COLUMN IF EXISTS forced_neutral_count_seller;
        SQL);
    }
}
