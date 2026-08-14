<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddSurveyorAndCustodianToListing extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE listing ADD COLUMN surveyor_party_id CHAR(36);
            ALTER TABLE listing ADD COLUMN custodian_party_id CHAR(36);
            ALTER TABLE listing ADD CONSTRAINT fk_listing_surveyor_party_id FOREIGN KEY (surveyor_party_id) REFERENCES party(id);
            ALTER TABLE listing ADD CONSTRAINT fk_listing_custodian_party_id FOREIGN KEY (custodian_party_id) REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        // MySQL requires an FK constraint be dropped before the column it
        // references can be dropped (Postgres's DROP COLUMN handles this
        // implicitly).
        $this->execMulti(<<<SQL
            ALTER TABLE listing DROP FOREIGN KEY fk_listing_surveyor_party_id;
            ALTER TABLE listing DROP FOREIGN KEY fk_listing_custodian_party_id;
            ALTER TABLE listing DROP COLUMN surveyor_party_id, DROP COLUMN custodian_party_id;
        SQL);
    }
}
