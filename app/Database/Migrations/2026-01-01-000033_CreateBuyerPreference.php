<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBuyerPreference extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE buyer_preference (
                id                  UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id            UUID NOT NULL UNIQUE REFERENCES party(id),
                preferred_categories TEXT,
                comfort_states       TEXT,
                budget_min           NUMERIC(14,2),
                budget_max           NUMERIC(14,2),
                created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
                updated_at          TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_buyer_preference_party ON buyer_preference (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS buyer_preference CASCADE;');
    }
}
