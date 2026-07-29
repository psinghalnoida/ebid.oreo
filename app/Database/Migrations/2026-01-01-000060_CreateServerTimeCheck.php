<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// Tech Stack §3.10 (Server Time Integrity): "All auction timing... is
// computed against a server clock synced to NTP against a verified
// time source, checked continuously. Any drift or manual clock
// adjustment beyond a defined tolerance triggers an automated alert to
// the Super Admin and is itself logged as an audit event."
class CreateServerTimeCheck extends Migration
{
    public function up()
    {
        $this->db->query(<<<SQL
            CREATE TABLE server_time_check (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                checked_at TIMESTAMPTZ NOT NULL DEFAULT now(),
                ntp_host TEXT NOT NULL,
                ntp_reachable BOOLEAN NOT NULL,
                local_time TIMESTAMPTZ NOT NULL,
                ntp_time TIMESTAMPTZ,
                drift_seconds NUMERIC(12,3),
                tolerance_seconds NUMERIC(12,3) NOT NULL,
                within_tolerance BOOLEAN,
                acknowledged_at TIMESTAMPTZ,
                acknowledged_by_party_id UUID REFERENCES party(id)
            );
            CREATE INDEX idx_server_time_check_checked_at ON server_time_check (checked_at DESC);
        SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS server_time_check;');
    }
}
