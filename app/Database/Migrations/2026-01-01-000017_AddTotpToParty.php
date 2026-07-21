<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTotpToParty extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            -- ⚠️ Stored in plain text for now — a real production
            -- deployment should encrypt this at rest (CodeIgniter's
            -- Encryption service, keyed off .env's encryption.key).
            -- Flagged as a known simplification, not silently accepted —
            -- see docs/DECISIONS.md.
            ALTER TABLE party ADD COLUMN totp_secret TEXT;
            ALTER TABLE party ADD COLUMN totp_enabled_at TIMESTAMPTZ;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE party DROP COLUMN IF EXISTS totp_secret, DROP COLUMN IF EXISTS totp_enabled_at;');
    }
}
