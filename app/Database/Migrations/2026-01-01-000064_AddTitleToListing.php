<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// BR-46: the AI pre-audit's "one-click 'Apply Title' action" needs
// somewhere to write a title to -- the listing table has never had
// one. Nullable and additive: a listing without a title continues to
// display exactly as it always has (category/subcategory composed),
// this only adds an optional override.
class AddTitleToListing extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE listing ADD COLUMN title TEXT;');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE listing DROP COLUMN title;');
    }
}
