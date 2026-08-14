<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// BR-54/PR-31: Anti-Money Laundering Transaction Monitoring — the three
// specific patterns named in the governing text, not the larger scoring/
// case-management platform discussed and explicitly deferred (see
// docs/DECISIONS.md).
class CreateAmlFlag extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE aml_flag (

                id                      CHAR(36) PRIMARY KEY,

                flag_type               ENUM('deposit_refund_cycle', 'kyc_inconsistent_deposit', 'shared_funding_source') NOT NULL,

                party_id CHAR(36) NOT NULL,

                detail                  TEXT NOT NULL,

                status                  ENUM('open', 'reviewed_no_action', 'reviewed_str_filed') NOT NULL DEFAULT 'open',

                reviewed_by_party_id CHAR(36),

                review_notes            TEXT,

                str_reference           TEXT,

                created_at              DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                reviewed_at             DATETIME(6),

                CONSTRAINT fk_aml_flag_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_aml_flag_reviewed_by_party_id FOREIGN KEY (reviewed_by_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_aml_flag_party ON aml_flag (party_id);
            CREATE INDEX idx_aml_flag_status ON aml_flag (status);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS aml_flag CASCADE;');
    }
}
