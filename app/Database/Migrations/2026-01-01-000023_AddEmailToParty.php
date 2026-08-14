<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class AddEmailToParty extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            ALTER TABLE party ADD COLUMN recovery_email TEXT;
        SQL);
    }

    public function down()
    {
        $this->db->query('ALTER TABLE party DROP COLUMN recovery_email;');
    }
}
