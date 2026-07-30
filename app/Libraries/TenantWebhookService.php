<?php

namespace App\Libraries;

use App\Models\TenantModel;
use App\Models\TenantWebhookDeliveryModel;

// PR-37: fires the webhook events named in its own Operational Sequence
// (listing.approved, sale_event.created, listing.archived, and this
// codebase's own settlement/dispute completion points), scoped to a
// Tenant's own registered webhook_url. At-least-once delivery with
// bounded retry -- the same "stage now, scheduler finalizes later"
// pattern as media_upload_job (PR-09) and payout bank cooling-off
// (BR-50), not a fire-and-forget HTTP call.
//
// Payload signing (X-TSX-Signature: HMAC-SHA256 of the raw body, using a
// per-tenant secret generated alongside webhook_url) is not explicitly
// required by PR-37's text -- added as a reasonable security default, the
// same category of unrequested-but-sensible choice as this codebase's
// other flagged defaults (OTP-attempt limit, settlement-stall window),
// not a settled business rule.
class TenantWebhookService
{
    private const MAX_ATTEMPTS = 5;
    private const HTTP_TIMEOUT_SECONDS = 5;
    // Simple fixed backoff, not exponential -- PR-37 doesn't specify a
    // retry cadence; this is a reasonable default, same flagging pattern
    // as the rest of this service's unrequested-but-sensible choices.
    private const RETRY_DELAY_MINUTES = 5;

    private TenantModel $tenantModel;
    private TenantWebhookDeliveryModel $deliveryModel;

    public function __construct()
    {
        $this->tenantModel = new TenantModel();
        $this->deliveryModel = new TenantWebhookDeliveryModel();
    }

    // Called from the existing lifecycle points (ListingLifecycleService,
    // SettlementService, DisputeService) -- a silent no-op for any tenant
    // that hasn't registered a webhook_url, which is most of them.
    public function fire(string $tenantId, string $eventType, array $payload): void
    {
        $tenant = $this->tenantModel->find($tenantId);
        if (!$tenant || empty($tenant['webhook_url'])) {
            return;
        }

        $body = json_encode(['event' => $eventType, 'data' => $payload], JSON_UNESCAPED_SLASHES);
        $delivery = $this->deliveryModel->createDelivery($tenantId, $eventType, $body);

        $this->attempt($delivery, $tenant);
    }

    // Wired into SchedulerService::runAll() -- retries every pending
    // delivery whose next_attempt_at has come due.
    public function retryDue(): array
    {
        $due = $this->deliveryModel->findDuePending();
        $retried = [];
        foreach ($due as $delivery) {
            $tenant = $this->tenantModel->find($delivery['tenant_id']);
            if (!$tenant || empty($tenant['webhook_url'])) {
                // Webhook URL was cleared since this delivery was queued
                // -- nothing left to retry against.
                $this->deliveryModel->markPermanentlyFailed($delivery['id'], (int) $delivery['attempts'], 'Tenant no longer has a webhook_url registered.');
                continue;
            }
            $this->attempt($delivery, $tenant);
            $retried[] = $delivery['id'];
        }
        return $retried;
    }

    private function attempt(array $delivery, array $tenant): void
    {
        $signature = hash_hmac('sha256', $delivery['payload'], $tenant['webhook_signing_secret'] ?? '');

        $ch = curl_init($tenant['webhook_url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $delivery['payload'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-TSX-Signature: sha256=' . $signature,
                'X-TSX-Event: ' . $delivery['event_type'],
            ],
            CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $attempts = (int) $delivery['attempts'] + 1;

        if ($httpCode >= 200 && $httpCode < 300) {
            $this->deliveryModel->markDelivered($delivery['id']);
            return;
        }

        $error = $curlError ?: "Received HTTP {$httpCode}";
        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->deliveryModel->markPermanentlyFailed($delivery['id'], $attempts, $error);
            (new AuditLogService())->log('tenant_webhook.delivery_abandoned', null, [
                'tenantId' => $delivery['tenant_id'], 'eventType' => $delivery['event_type'], 'attempts' => $attempts, 'error' => $error,
            ]);
            return;
        }

        $nextAttemptAt = (new \DateTimeImmutable())->modify('+' . self::RETRY_DELAY_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $this->deliveryModel->markAttemptFailed($delivery['id'], $attempts, $error, $nextAttemptAt);
    }
}
