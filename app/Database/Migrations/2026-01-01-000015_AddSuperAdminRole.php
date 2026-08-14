<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuperAdminRole extends Migration
{
    public function up()
    {
        // MySQL has no separate enum type -- the enum is inlined directly
        // on the column (see CreatePartyRole.php), so "adding a value" is a
        // MODIFY COLUMN restating the complete expanded value list.
        $this->db->query("ALTER TABLE party_role MODIFY COLUMN role ENUM(
            'buyer', 'seller', 'bidder', 'vendor', 'customer',
            'auctioneer', 'service_provider', 'surveyor', 'financier',
            'tenant_admin', 'super_admin'
        ) NOT NULL;");
    }

    public function down()
    {
        // Postgres does not support removing an enum value directly.
        // No-op — acceptable since this is additive-only and harmless to
        // leave in place even if unused.
    }
}
