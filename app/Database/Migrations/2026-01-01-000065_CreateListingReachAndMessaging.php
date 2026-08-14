<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// D-105: builds the real backend behind "Lot Reach & Interest" -- a
// screen in the design handoff package with no matching feature in the
// app at all (see docs/design/CLAUDE_DESIGN_HANDOFF.md). Two things a
// Market Maker (seller) needs that didn't exist anywhere:
//   1. Per-listing reach analytics -- view count, who's favorited it,
//      and (new here) which buyers match it on their own saved CLV
//      preferences, reversing the direction ClvMatchingService already
//      ran the other way (buyer -> matching listings).
//   2. A real way to reach those matched buyers -- an in-app message,
//      not real SMS/email (no provider connected; see D-104's own
//      audit), delivered to a real inbox a buyer can actually read.
class CreateListingReachAndMessaging extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        // view_count: a simple rolling total, incremented on every real
        // GET to the listing page regardless of who's viewing (logged in
        // or not) -- this is the aggregate stat shown on the seller's
        // reach dashboard ("Total Views"). Additive, defaults to 0.
        $this->db->query('ALTER TABLE listing ADD COLUMN view_count INTEGER NOT NULL DEFAULT 0;');

        // listing_view: per-party granularity, logged-in parties only --
        // the design mockup shows "Viewed Lot" as a yes/no flag *per
        // matched buyer*, which an aggregate counter alone can't answer.
        // Anonymous views still increment listing.view_count above but
        // don't get a row here (there's no party to attribute it to).
        $this->execMulti(<<<SQL
            CREATE TABLE listing_view (

                id          CHAR(36) PRIMARY KEY,

                listing_id CHAR(36) NOT NULL,

                party_id CHAR(36) NOT NULL,

                viewed_at   DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                UNIQUE (listing_id, party_id),

                CONSTRAINT fk_listing_view_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id),

                CONSTRAINT fk_listing_view_party_id FOREIGN KEY (party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_listing_view_listing ON listing_view (listing_id);
            CREATE INDEX idx_listing_view_party ON listing_view (party_id);
        SQL);

        // seller_message: one row per bulk-send action.
        // seller_message_recipient: one row per matched buyer the message
        // actually went to -- this is the real inbox delivery record, and
        // read_at is what a buyer's inbox uses to show unread state.
        $this->execMulti(<<<SQL
            CREATE TABLE seller_message (

                id                    CHAR(36) PRIMARY KEY,

                listing_id CHAR(36) NOT NULL,

                seller_party_id CHAR(36) NOT NULL,

                message_body          TEXT NOT NULL,

                matched_buyer_count   INTEGER NOT NULL,

                created_at            DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                CONSTRAINT fk_seller_message_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id),

                CONSTRAINT fk_seller_message_seller_party_id FOREIGN KEY (seller_party_id) REFERENCES party(id)
            );

            CREATE TABLE seller_message_recipient (

                id                  CHAR(36) PRIMARY KEY,

                seller_message_id CHAR(36) NOT NULL,

                buyer_party_id CHAR(36) NOT NULL,

                delivered_at        DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                read_at             DATETIME(6),

                CONSTRAINT fk_seller_message_recipient_seller_message_id FOREIGN KEY (seller_message_id) REFERENCES seller_message(id),

                CONSTRAINT fk_seller_message_recipient_buyer_party_id FOREIGN KEY (buyer_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_seller_message_listing ON seller_message (listing_id);
            CREATE INDEX idx_seller_message_recipient_buyer ON seller_message_recipient (buyer_party_id);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            DROP TABLE IF EXISTS seller_message_recipient;
            DROP TABLE IF EXISTS seller_message;
            DROP TABLE IF EXISTS listing_view;
        SQL);
        $this->db->query('ALTER TABLE listing DROP COLUMN view_count;');
    }
}
