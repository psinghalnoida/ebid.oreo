<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// D-115: the first real, persistent consumer of the domain-event layer
// (App\Libraries\DomainEvents + CodeIgniter's native Events facade).
// Deliberately distinct from audit_log (D-16/D-45's hash-chained
// compliance ledger, actor-driven, tamper-evident): this is a plain
// technical event store — every domain event fired, for whichever
// future consumer (analytics, AI, a real notification queue) wants to
// replay or subscribe to the stream without coupling to the publisher.
class CreateDomainEventLog extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE domain_event_log (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                event_name      TEXT NOT NULL,
                payload         JSONB NOT NULL,
                occurred_at     TIMESTAMPTZ NOT NULL DEFAULT clock_timestamp(),
                sequence_number BIGSERIAL
            );

            CREATE INDEX idx_domain_event_log_event_name ON domain_event_log (event_name);
            CREATE INDEX idx_domain_event_log_occurred_at ON domain_event_log (occurred_at);
            CREATE INDEX idx_domain_event_log_sequence ON domain_event_log (sequence_number);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS domain_event_log CASCADE;');
    }
}
