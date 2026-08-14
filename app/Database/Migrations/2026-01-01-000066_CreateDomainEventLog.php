<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
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
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE domain_event_log (
                id              CHAR(36) PRIMARY KEY,
                event_name      VARCHAR(255) NOT NULL,
                payload         JSON NOT NULL,
                occurred_at     DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                sequence_number BIGINT NOT NULL AUTO_INCREMENT UNIQUE
            );

            CREATE INDEX idx_domain_event_log_event_name ON domain_event_log (event_name);
            CREATE INDEX idx_domain_event_log_occurred_at ON domain_event_log (occurred_at);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS domain_event_log CASCADE;');
    }
}
