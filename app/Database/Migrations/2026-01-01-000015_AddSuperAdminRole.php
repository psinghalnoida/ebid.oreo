<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuperAdminRole extends Migration
{
    public function up()
    {
        // Postgres requires ALTER TYPE ... ADD VALUE to run outside an
        // explicit transaction block in older versions; CI4's migration
        // runner wraps this in a transaction, which works fine on modern
        // Postgres (16, used throughout this project's testing) but is
        // flagged here in case it needs adjustment on a different version.
        $this->db->query("ALTER TYPE party_role_type ADD VALUE IF NOT EXISTS 'super_admin';");
    }

    public function down()
    {
        // Postgres does not support removing an enum value directly.
        // No-op — acceptable since this is additive-only and harmless to
        // leave in place even if unused.
    }
}
