<?php

namespace App\Models;

use CodeIgniter\Model;

// PR-37: at-least-once webhook delivery log with bounded retry, the same
// "stage now, scheduler finalizes later" shape as media_upload_job.
class TenantWebhookDeliveryModel extends Model
{
    protected $table            = 'tenant_webhook_delivery';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'id', 'tenant_id', 'event_type', 'payload', 'status',
        'attempts', 'last_error', 'next_attempt_at', 'delivered_at',
    ];

    public function createDelivery(string $tenantId, string $eventType, string $payloadJson): array
    {
        $id = \App\Libraries\Uuid::v4();
        $this->insert([
            'id' => $id, 'tenant_id' => $tenantId, 'event_type' => $eventType, 'payload' => $payloadJson,
        ]);
        return $this->find($id);
    }

    public function markDelivered(string $id): void
    {
        $this->update($id, ['status' => 'delivered', 'delivered_at' => date('Y-m-d H:i:s')]);
    }

    public function markAttemptFailed(string $id, int $attempts, string $error, string $nextAttemptAt): void
    {
        $this->update($id, [
            'attempts' => $attempts, 'last_error' => $error, 'next_attempt_at' => $nextAttemptAt,
        ]);
    }

    public function markPermanentlyFailed(string $id, int $attempts, string $error): void
    {
        $this->update($id, ['status' => 'failed', 'attempts' => $attempts, 'last_error' => $error]);
    }

    public function findDuePending(int $limit = 50): array
    {
        return $this->where('status', 'pending')
            ->where('next_attempt_at <=', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }

    public function findForTenant(string $tenantId, int $limit = 50): array
    {
        return $this->where('tenant_id', $tenantId)->orderBy('created_at', 'DESC')->limit($limit)->findAll();
    }
}
