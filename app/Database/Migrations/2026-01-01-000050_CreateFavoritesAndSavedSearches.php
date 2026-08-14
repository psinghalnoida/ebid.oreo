<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Phase 3C+: favorites/watchlist, saved searches, and search history —
// none of these existed before. Notification-on-match/price-drop is
// deliberately not attempted here — it needs a real notification
// system (email/SMS/push) that doesn't exist, the same category of gap
// as the SMS provider itself.
class CreateFavoritesAndSavedSearches extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE listing_favorite (

                id          CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                listing_id CHAR(36) NOT NULL,

                created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                UNIQUE(party_id, listing_id),

                CONSTRAINT fk_listing_favorite_party_id FOREIGN KEY (party_id) REFERENCES party(id),

                CONSTRAINT fk_listing_favorite_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id)
            );
            CREATE INDEX idx_listing_favorite_party ON listing_favorite (party_id);

            CREATE TABLE saved_search (

                id          CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                label       TEXT NOT NULL,

                filters     TEXT NOT NULL,

                created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_saved_search_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );
            CREATE INDEX idx_saved_search_party ON saved_search (party_id);

            CREATE TABLE search_history (

                id          CHAR(36) PRIMARY KEY,

                party_id CHAR(36) NOT NULL,

                filters     TEXT NOT NULL,

                created_at  DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_search_history_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );
            CREATE INDEX idx_search_history_party ON search_history (party_id, created_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS search_history CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS saved_search CASCADE;');
        $this->db->query('DROP TABLE IF EXISTS listing_favorite CASCADE;');
    }
}
