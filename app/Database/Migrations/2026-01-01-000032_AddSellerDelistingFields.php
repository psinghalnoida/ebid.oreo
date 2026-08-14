<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddSellerDelistingFields extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party ADD COLUMN seller_delisted_at DATETIME(6);
            ALTER TABLE party ADD COLUMN seller_delisted_reason TEXT;
            ALTER TABLE party ADD COLUMN seller_delisted_by_party_id CHAR(36);
            ALTER TABLE party ADD CONSTRAINT fk_party_seller_delisted_by_party_id FOREIGN KEY (seller_delisted_by_party_id) REFERENCES party(id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party DROP FOREIGN KEY fk_party_seller_delisted_by_party_id;
            ALTER TABLE party DROP COLUMN seller_delisted_at;
            ALTER TABLE party DROP COLUMN seller_delisted_reason;
            ALTER TABLE party DROP COLUMN seller_delisted_by_party_id;
        SQL);
    }
}
