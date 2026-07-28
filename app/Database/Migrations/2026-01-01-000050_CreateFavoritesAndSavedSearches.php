<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Phase 3C+: favorites/watchlist, saved searches, and search history —
// none of these existed before. Notification-on-match/price-drop is
// deliberately not attempted here — it needs a real notification
// system (email/SMS/push) that doesn't exist, the same category of gap
// as the SMS provider itself.
class CreateFavoritesAndSavedSearches extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE listing_favorite (
                id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id    UUID NOT NULL REFERENCES party(id),
                listing_id  UUID NOT NULL REFERENCES listing(id),
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
                UNIQUE(party_id, listing_id)
            );
            CREATE INDEX idx_listing_favorite_party ON listing_favorite (party_id);

            CREATE TABLE saved_search (
                id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id    UUID NOT NULL REFERENCES party(id),
                label       TEXT NOT NULL,
                filters     TEXT NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
            );
            CREATE INDEX idx_saved_search_party ON saved_search (party_id);

            CREATE TABLE search_history (
                id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id    UUID NOT NULL REFERENCES party(id),
                filters     TEXT NOT NULL,
                created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
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
