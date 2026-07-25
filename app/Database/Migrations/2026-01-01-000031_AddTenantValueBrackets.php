<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTenantValueBrackets extends Migration
{
    public function up()
    {
        // Party-level Crawl-Back/Shadow-Ban columns already exist (see
        // migration 009, AddRatingStateToParty) — laid down early in
        // the project anticipating this build, but never wired to real
        // logic until now (found before writing any duplicate schema,
        // not after). Only the tenant-level value brackets are
        // genuinely new.
        $this->db->query(<<<SQL
            ALTER TABLE tenant ADD COLUMN low_bracket_max NUMERIC(14,2);
            ALTER TABLE tenant ADD COLUMN medium_bracket_max NUMERIC(14,2);
        SQL);
    }

    public function down()
    {
        $this->db->query(<<<SQL
            ALTER TABLE tenant DROP COLUMN IF EXISTS low_bracket_max;
            ALTER TABLE tenant DROP COLUMN IF EXISTS medium_bracket_max;
        SQL);
    }
}
