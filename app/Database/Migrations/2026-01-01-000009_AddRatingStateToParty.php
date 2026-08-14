<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddRatingStateToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party ADD COLUMN offence_count_buyer INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN offence_count_seller INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN crawl_back_active_buyer BOOLEAN NOT NULL DEFAULT false;
            ALTER TABLE party ADD COLUMN crawl_back_clean_required_buyer INTEGER;
            ALTER TABLE party ADD COLUMN crawl_back_clean_completed_buyer INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN crawl_back_active_seller BOOLEAN NOT NULL DEFAULT false;
            ALTER TABLE party ADD COLUMN crawl_back_clean_required_seller INTEGER;
            ALTER TABLE party ADD COLUMN crawl_back_clean_completed_seller INTEGER NOT NULL DEFAULT 0;

            ALTER TABLE party ADD COLUMN shadow_banned_at_buyer DATETIME(6);
            ALTER TABLE party ADD COLUMN shadow_banned_at_seller DATETIME(6);

            ALTER TABLE party ADD COLUMN deposit_override_amount NUMERIC(14,2);

            ALTER TABLE party ADD COLUMN forced_neutral_count_buyer INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE party ADD COLUMN forced_neutral_count_seller INTEGER NOT NULL DEFAULT 0;
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party
                DROP COLUMN offence_count_buyer,
                DROP COLUMN offence_count_seller,
                DROP COLUMN crawl_back_active_buyer,
                DROP COLUMN crawl_back_clean_required_buyer,
                DROP COLUMN crawl_back_clean_completed_buyer,
                DROP COLUMN crawl_back_active_seller,
                DROP COLUMN crawl_back_clean_required_seller,
                DROP COLUMN crawl_back_clean_completed_seller,
                DROP COLUMN shadow_banned_at_buyer,
                DROP COLUMN shadow_banned_at_seller,
                DROP COLUMN deposit_override_amount,
                DROP COLUMN forced_neutral_count_buyer,
                DROP COLUMN forced_neutral_count_seller;
        SQL);
    }
}
