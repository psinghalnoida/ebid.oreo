<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddTenantValueBrackets extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        // Party-level Crawl-Back/Shadow-Ban columns already exist (see
        // migration 009, AddRatingStateToParty) — laid down early in
        // the project anticipating this build, but never wired to real
        // logic until now (found before writing any duplicate schema,
        // not after). Only the tenant-level value brackets are
        // genuinely new.
        $this->execMulti(<<<SQL
            ALTER TABLE tenant ADD COLUMN low_bracket_max NUMERIC(14,2);
            ALTER TABLE tenant ADD COLUMN medium_bracket_max NUMERIC(14,2);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE tenant DROP COLUMN low_bracket_max;
            ALTER TABLE tenant DROP COLUMN medium_bracket_max;
        SQL);
    }
}
