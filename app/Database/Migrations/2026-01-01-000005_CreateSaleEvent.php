<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

class CreateSaleEvent extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE sale_event (

                id                      CHAR(36) PRIMARY KEY,

                listing_id CHAR(36) NOT NULL,

                tenant_id CHAR(36) NOT NULL,

                ern                     VARCHAR(255) NOT NULL UNIQUE,

                sale_format             ENUM('buy_now', 'express', 'easy', 'tender') NOT NULL,

                status                  ENUM(
                'pending_approval', 'grace_period', 'active', 'closed_sold',
                'cancelled', 'cycle_ended_unsold'
            ) NOT NULL DEFAULT 'pending_approval',

                expected_value           NUMERIC(14,2),

                reserve_value             NUMERIC(14,2),

                emd_percent               NUMERIC(4,2) NOT NULL DEFAULT 10.00
                                             CHECK (emd_percent = 10.00),

                dynamic_time_trigger_minutes    INTEGER DEFAULT 10,

                dynamic_time_extension_minutes  INTEGER DEFAULT 2,

                intensity_mode_active            BOOLEAN NOT NULL DEFAULT false,

                result_mode                TEXT CHECK (result_mode IN ('instant_close', 'approval_required')),

                current_price              NUMERIC(14,2),

                current_high_bidder_party_id CHAR(36),

                grace_period_ends_at        DATETIME(6),

                scheduled_start_at          DATETIME(6),

                scheduled_end_at             DATETIME(6),

                actual_closed_at              DATETIME(6),

                rejection_reason              TEXT,

                emergency_stopped_at           DATETIME(6),

                emergency_stop_reason          TEXT,

                created_at                     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                updated_at                     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),


                -- BR-?: exactly one open (pending/grace/active) sale event per
                -- listing. MySQL has no partial/filtered unique index, so this
                -- generated marker column + compound UNIQUE KEY replicates the
                -- original Postgres partial-unique-index semantics exactly:
                -- MySQL's default NULL-distinct behavior means rows where the
                -- marker is NULL (status not in the open set) never collide,
                -- while rows where it's 'Y' do.
                open_sale_marker CHAR(1) GENERATED ALWAYS AS (
                    CASE WHEN status IN ('pending_approval', 'grace_period', 'active') THEN 'Y' END
                ) STORED,

                UNIQUE KEY uq_one_open_sale_event_per_listing (listing_id, open_sale_marker),

                CONSTRAINT fk_sale_event_listing_id FOREIGN KEY (listing_id) REFERENCES listing(id),

                CONSTRAINT fk_sale_event_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenant(id),

                CONSTRAINT fk_sale_event_current_high_bidder_party_id FOREIGN KEY (current_high_bidder_party_id) REFERENCES party(id)
            );

            CREATE INDEX idx_sale_event_tenant_status ON sale_event (tenant_id, status);
            CREATE INDEX idx_sale_event_listing ON sale_event (listing_id);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS sale_event CASCADE;');
    }
}
