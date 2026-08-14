<?php

namespace App\Database\Migrations;

use App\Libraries\MultiStatementMigrationTrait;
use CodeIgniter\Database\Migration;

// Tech Stack §3.10 (Server Time Integrity): "All auction timing... is
// computed against a server clock synced to NTP against a verified
// time source, checked continuously. Any drift or manual clock
// adjustment beyond a defined tolerance triggers an automated alert to
// the Super Admin and is itself logged as an audit event."
class CreateServerTimeCheck extends Migration
{
    use MultiStatementMigrationTrait;

    public function up()
    {
        $this->execMulti(<<<SQL
            CREATE TABLE server_time_check (

                id CHAR(36) PRIMARY KEY,

                checked_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),

                ntp_host TEXT NOT NULL,

                ntp_reachable BOOLEAN NOT NULL,

                local_time DATETIME(6) NOT NULL,

                ntp_time DATETIME(6),

                drift_seconds NUMERIC(12,3),

                tolerance_seconds NUMERIC(12,3) NOT NULL,

                within_tolerance BOOLEAN,

                acknowledged_at DATETIME(6),

                acknowledged_by_party_id CHAR(36),

                CONSTRAINT fk_server_time_check_acknowledged_by_party_id FOREIGN KEY (acknowledged_by_party_id) REFERENCES party(id)
            );
            CREATE INDEX idx_server_time_check_checked_at ON server_time_check (checked_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS server_time_check;');
    }
}
