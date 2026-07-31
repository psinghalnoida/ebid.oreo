<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\TenantModel;
use App\Models\PartyModel;
use App\Models\TenantWebhookDeliveryModel;
use App\Libraries\ApiCredentialService;
use App\Libraries\TenantWebhookService;

// BR-62-66/PR-37: the self-hosted OAuth2 client-credentials substitution
// (ApiCredentialService) and webhook delivery/retry (TenantWebhookService)
// against real data. The actual push/pull governance reuse in
// TenantApiController is verified via real HTTP separately, the same
// split this session uses throughout.
class TestTenantApi extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:tenantapi';
    protected $description = 'Runs BR-62-66/PR-37 API credential issuance, token validation, tier gating, and webhook delivery/retry against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $tenantModel = new TenantModel();
        $partyModel = new PartyModel();
        $creds = new ApiCredentialService();

        CLI::write('=== BR-66: tier gating helpers ===', 'yellow');
        $this->assert(TenantModel::hasApiAccess('coco_starter') === false, 'CoCo Starter has no API access');
        $this->assert(TenantModel::hasApiAccess('tsx_launch') === true, 'TSX Launch has API access');
        $this->assert(TenantModel::canPushListings('tsx_launch') === false, 'TSX Launch cannot push listings (read-only)');
        $this->assert(TenantModel::canPushListings('tsx_growth') === true, 'TSX Growth can push listings');
        $this->assert(TenantModel::canPushSaleEvents('tsx_growth') === false, 'TSX Growth cannot push Sale Systems');
        $this->assert(TenantModel::canPushSaleEvents('tsx_enterprise') === true, 'TSX Enterprise can push Sale Systems');

        CLI::write("\n=== Setup: an Enterprise-tier tenant ===", 'yellow');
        $tenant = $tenantModel->createTenant([
            'name' => 'API Test Tenant', 'tenant_class' => 'general',
            'subdomain' => 'apitesttenant', 'subscription_tier' => 'tsx_enterprise',
        ]);
        $admin = $partyModel->createParty('+919888803001');

        CLI::write("\n=== BR-64: credential issuance + OAuth2 client-credentials flow ===", 'yellow');
        $issued = $creds->issueCredential($tenant['id'], $admin['id']);
        $clientId = $issued['credential']['client_id'];
        $clientSecret = $issued['clientSecret'];
        $this->assert(str_starts_with($clientId, 'tsx_'), 'A real client_id was generated');
        $this->assert(strlen($clientSecret) >= 32, 'A real, sufficiently long client_secret was generated');

        $token = $creds->authenticate($clientId, $clientSecret);
        $this->assert(!empty($token['access_token']), 'A real access token was issued');
        $this->assert($token['token_type'] === 'Bearer', 'Token type is Bearer');
        $this->assert((int) $token['expires_in'] > 0, 'Token carries a real expiry');

        $wrongSecretRejected = false;
        try {
            $creds->authenticate($clientId, 'wrong-secret-entirely');
        } catch (\RuntimeException $e) {
            $wrongSecretRejected = str_contains($e->getMessage(), 'invalid_client');
        }
        $this->assert($wrongSecretRejected, 'An incorrect client_secret is genuinely rejected');

        CLI::write("\n=== BR-64: hard tenant-scoping at token validation ===", 'yellow');
        $claims = $creds->validateToken($token['access_token']);
        $this->assert($claims !== null, 'A genuine access token validates successfully');
        $this->assert($claims['tenantId'] === $tenant['id'], 'The validated token is hard-scoped to the issuing tenant');

        $tamperedRejected = $creds->validateToken($token['access_token'] . 'x') === null;
        $this->assert($tamperedRejected, 'A tampered token (signature no longer matches) is rejected');

        CLI::write("\n=== BR-64: revocation takes effect immediately, not just at next refresh ===", 'yellow');
        $creds->revokeCredential($issued['credential']['id'], $admin['id']);
        $afterRevoke = $creds->validateToken($token['access_token']);
        $this->assert($afterRevoke === null, 'An outstanding token for a just-revoked credential is rejected immediately');

        $revokedAuthRejected = false;
        try {
            $creds->authenticate($clientId, $clientSecret);
        } catch (\RuntimeException $e) {
            $revokedAuthRejected = true;
        }
        $this->assert($revokedAuthRejected, 'A revoked credential can no longer authenticate at all');

        CLI::write("\n=== PR-37: webhook delivery -- silent no-op with no webhook_url ===", 'yellow');
        $deliveryModel = new TenantWebhookDeliveryModel();
        $webhooks = new TenantWebhookService();
        $webhooks->fire($tenant['id'], 'listing.approved', ['listingId' => 'test-1']);
        $this->assert(count($deliveryModel->findForTenant($tenant['id'])) === 0, 'No delivery row created when the tenant has no webhook_url registered');

        CLI::write("\n=== PR-37: webhook delivery -- real HTTP attempt, logged and retried on failure ===", 'yellow');
        // An address nothing listens on in this environment -- a real
        // connection-refused failure, not a mock.
        $tenantModel->update($tenant['id'], [
            'webhook_url' => 'http://127.0.0.1:1/webhooks/tsx',
            'webhook_signing_secret' => 'test-signing-secret',
        ]);
        $tenant = $tenantModel->find($tenant['id']);
        $webhooks->fire($tenant['id'], 'listing.approved', ['listingId' => 'test-2']);

        $deliveries = $deliveryModel->findForTenant($tenant['id']);
        $this->assert(count($deliveries) === 1, 'A delivery row was created for the tenant with a webhook_url');
        $delivery = $deliveries[0];
        $this->assert($delivery['status'] === 'pending', 'A failed first attempt against an unreachable URL leaves status pending for retry');
        $this->assert((int) $delivery['attempts'] === 1, 'Attempt count incremented to 1');
        $this->assert(!empty($delivery['last_error']), 'The real connection failure was recorded as last_error');
        $this->assert(strtotime($delivery['next_attempt_at']) > time(), 'next_attempt_at is scheduled in the future, not immediate');

        CLI::write("\n=== PR-37: bounded retry eventually gives up ===", 'yellow');
        // Force it due now, repeatedly, past MAX_ATTEMPTS (5).
        for ($i = 0; $i < 5; $i++) {
            $deliveryModel->update($delivery['id'], ['next_attempt_at' => date('Y-m-d H:i:s', time() - 1)]);
            $webhooks->retryDue();
        }
        $final = $deliveryModel->find($delivery['id']);
        $this->assert($final['status'] === 'failed', 'After repeated failures past the retry cap, the delivery is marked permanently failed');
        $this->assert((int) $final['attempts'] === 5, 'Exactly 5 attempts were made before giving up');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  ✓ {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  ✗ {$msg}", 'red');
        }
    }
}
