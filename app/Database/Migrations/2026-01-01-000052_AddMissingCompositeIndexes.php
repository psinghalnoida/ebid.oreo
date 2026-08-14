<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Phase 3D remainder: composite indexes matching real, already-existing
// query patterns that were only served by a single-column (or no) index.
// Each one below is traced to an actual WHERE+ORDER BY combination in the
// codebase, not speculative — see the comment on each.
class AddMissingCompositeIndexes extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            -- MyActivityController::mySales/myPurchases/CSV exports filter
            -- by buyer_party_id or seller_party_id and always order by
            -- created_at DESC. The existing idx_settlement_buyer/seller
            -- are single-column only.
            CREATE INDEX idx_settlement_buyer_created ON settlement (buyer_party_id, created_at DESC);
            CREATE INDEX idx_settlement_seller_created ON settlement (seller_party_id, created_at DESC);

            -- AccountController::earnings filters by seller_party_id +
            -- status='completed' + completed_at range.
            CREATE INDEX idx_settlement_seller_status_completed ON settlement (seller_party_id, status, completed_at);

            -- StandingReviewService::hasOpenCase, SellerManagementController,
            -- and UserController::detail all filter dispute by
            -- respondent_party_id (+ category, + status) — previously had
            -- NO index at all on respondent_party_id.
            CREATE INDEX idx_dispute_respondent_category_status ON dispute (respondent_party_id, category, status);
        SQL);
    }

    public function down()
    {
        $this->execMulti(<<<SQL
            DROP INDEX IF EXISTS idx_settlement_buyer_created ON settlement;
            DROP INDEX IF EXISTS idx_settlement_seller_created ON settlement;
            DROP INDEX IF EXISTS idx_settlement_seller_status_completed ON settlement;
            DROP INDEX IF EXISTS idx_dispute_respondent_category_status ON dispute;
        SQL);
    }
}
