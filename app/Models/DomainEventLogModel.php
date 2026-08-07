<?php

namespace App\Models;

use CodeIgniter\Model;

class DomainEventLogModel extends Model
{
    protected $table            = 'domain_event_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'event_name', 'payload', 'occurred_at'];

    public function record(string $eventName, array $payload): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'event_name' => $eventName,
            'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
        return $this->find($id);
    }

    public function findByEventName(string $eventName): array
    {
        return $this->where('event_name', $eventName)->orderBy('sequence_number', 'ASC')->findAll();
    }
}
