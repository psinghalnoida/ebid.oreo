<?php

namespace App\Models;

use CodeIgniter\Model;

class ServerTimeCheckModel extends Model
{
    protected $table            = 'server_time_check';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'ntp_host', 'ntp_reachable', 'local_time', 'ntp_time',
        'drift_seconds', 'tolerance_seconds', 'within_tolerance',
        'acknowledged_at', 'acknowledged_by_party_id',
    ];

    public function findUnacknowledgedDriftAlerts(): array
    {
        return $this->where('within_tolerance', false)
            ->where('acknowledged_at', null)
            ->orderBy('checked_at', 'DESC')
            ->findAll();
    }
}
