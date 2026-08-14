<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddTotpToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            -- ⚠️ Stored in plain text for now — a real production
            -- deployment should encrypt this at rest (CodeIgniter's
            -- Encryption service, keyed off .env's encryption.key).
            -- Flagged as a known simplification, not silently accepted —
            -- see docs/DECISIONS.md.
            ALTER TABLE party ADD COLUMN totp_secret TEXT;
            ALTER TABLE party ADD COLUMN totp_enabled_at DATETIME(6);
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE party DROP COLUMN totp_secret, DROP COLUMN totp_enabled_at;');
    }
}
