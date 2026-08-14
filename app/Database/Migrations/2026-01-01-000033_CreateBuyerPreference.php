<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateBuyerPreference extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE buyer_preference (

                id                  CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL UNIQUE,

                preferred_categories TEXT,

                comfort_states       TEXT,

                budget_min           NUMERIC(14,2),

                budget_max           NUMERIC(14,2),

                created_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at          DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_buyer_preference_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_buyer_preference_party ON buyer_preference (party_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS buyer_preference CASCADE;');
    }
}
