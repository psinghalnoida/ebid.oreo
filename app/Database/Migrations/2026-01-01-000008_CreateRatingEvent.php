<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRatingEvent extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TYPE rating_role AS ENUM ('star_rating', 'seller_star_rating');
            CREATE TYPE rating_event_type AS ENUM ('upgrade', 'downgrade', 'forced_neutral');
            CREATE TYPE rating_event_status AS ENUM ('applied', 'pending_tenant_approval', 'pending_super_admin_approval', 'rejected');

            CREATE TABLE rating_event (
                id                      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                party_id                UUID NOT NULL REFERENCES party(id),
                rating_role             rating_role NOT NULL,
                event_type              rating_event_type NOT NULL,
                previous_value          NUMERIC(2,1) NOT NULL,
                new_value                NUMERIC(2,1) NOT NULL CHECK (new_value >= 0 AND new_value <= 5),
                reason                    TEXT NOT NULL,
                status                     rating_event_status NOT NULL DEFAULT 'applied',
                tenant_admin_approved_by    UUID REFERENCES party(id),
                tenant_admin_approved_at    TIMESTAMPTZ,
                super_admin_approved_by      UUID REFERENCES party(id),
                super_admin_approved_at      TIMESTAMPTZ,
                appealed_at                   TIMESTAMPTZ,
                appeal_outcome                 TEXT,
                related_sale_event_id           UUID REFERENCES sale_event(id),
                created_at                       TIMESTAMPTZ NOT NULL DEFAULT now(),
                applied_at                        TIMESTAMPTZ
            );

            CREATE INDEX idx_rating_event_party ON rating_event (party_id, rating_role, created_at DESC);
            CREATE INDEX idx_rating_event_pending ON rating_event (status)
                WHERE status IN ('pending_tenant_approval', 'pending_super_admin_approval');
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS rating_event CASCADE;');
        $this->db->query('DROP TYPE IF EXISTS rating_event_status;');
        $this->db->query('DROP TYPE IF EXISTS rating_event_type;');
        $this->db->query('DROP TYPE IF EXISTS rating_role;');
    }
}
