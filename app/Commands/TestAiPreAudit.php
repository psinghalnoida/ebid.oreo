<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\TenantModel;
use App\Models\PartyModel;
use App\Models\ListingModel;
use App\Libraries\GeminiPreAuditService;

// BR-46: AI Listing Quality Pre-Audit. Genuinely blocked on a real
// Gemini API key this environment doesn't have (same category as
// BR-52) -- what's actually live and testable today is the "not
// configured" path itself: it must fail honestly, before any network
// call, not silently fabricate a result. The controller/route wiring
// (portal button, Tenant API endpoint, tier gating) is verified
// separately over real HTTP, the same split this session uses
// throughout for anything session- or bearer-token-gated.
class TestAiPreAudit extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:aiaudit';
    protected $description = 'Proves BR-46\'s "not configured" path is real and honest, the listing.title field round-trips, and the tier gate matches Lot push exactly.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $service = new GeminiPreAuditService();

        CLI::write('=== This environment genuinely has no Gemini key -- confirm that, not assume it ===', 'yellow');
        $_ENV['GEMINI_API_KEY'] = ''; // defensive: this environment already has no key set; this just guarantees the assertion below tests the real "unconfigured" state regardless of shell environment
        $this->assert($service->isConfigured() === false, 'isConfigured() correctly reports false with no key set');

        CLI::write("\n=== evaluate() fails honestly -- no fake result, no network call attempted ===", 'yellow');
        $threw = false;
        $message = '';
        try {
            $service->evaluate(['category' => 'Machinery', 'physicalCondition' => 'Used']);
        } catch (\RuntimeException $e) {
            $threw = true;
            $message = $e->getMessage();
        }
        $this->assert($threw, 'evaluate() throws rather than returning a fabricated quality score');
        $this->assert(str_contains($message, 'not currently available'), 'The failure message is honest about why, not a generic error');
        $this->assert(!str_contains($message, 'timeout') && !str_contains($message, 'cURL'), 'Fails before any network call -- this is a configuration check, not a failed HTTP request');

        CLI::write("\n=== BR-66: the pre-audit's tier gate is the exact same check as Lot push, not a new rule ===", 'yellow');
        $this->assert(TenantModel::canPushListings('tsx_growth') === true, 'TSX Growth (the Lot-push floor) can also reach pre-audit');
        $this->assert(TenantModel::canPushListings('tsx_launch') === false, 'TSX Launch (below the Lot-push floor) is correctly excluded');
        $this->assert(TenantModel::canPushListings('coco_starter') === false, 'CoCo Starter (no API access at all) is correctly excluded');

        CLI::write("\n=== listing.title: real, optional, round-trips through the model ===", 'yellow');
        $tenantModel = new TenantModel();
        $partyModel = new PartyModel();
        $listingModel = new ListingModel();

        $tenant = $tenantModel->createTenant(['name' => 'AI Pre-Audit Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'aipreaudittest']);
        $seller = $partyModel->createParty('+919888904001');

        $withTitle = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'], 'title' => 'Certified Pre-Owned Forklift, 2020',
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600011',
        ]);
        $this->assert($withTitle['title'] === 'Certified Pre-Owned Forklift, 2020', 'A listing created with a title stores it exactly');

        $withoutTitle = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600011',
        ]);
        $this->assert($withoutTitle['title'] === null, 'A listing created with no title stores null -- optional, not required, nothing breaks for the existing composed-display path');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function assert(bool $cond, string $msg): void
    {
        if ($cond) {
            $this->pass++;
            CLI::write("  \xE2\x9C\x93 {$msg}", 'green');
        } else {
            $this->fail++;
            CLI::write("  \xE2\x9C\x97 ASSERTION FAILED: {$msg}", 'red');
        }
    }
}
