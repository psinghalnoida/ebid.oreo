<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Models\TenantModel;
use App\Models\ListingModel;
use App\Models\SaleEventModel;
use App\Models\EmdHoldModel;
use App\Models\PartyRoleModel;
use App\Libraries\SuperAdminAuthService;
use App\Libraries\BiddingService;
use App\Libraries\OfferService;

class TestTier3 extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:tier3';
    protected $description = 'Runs Super Admin TOTP auth and conflict-of-interest blocks against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $tenantModel = new TenantModel();
        $listingModel = new ListingModel();
        $saleEventModel = new SaleEventModel();
        $emdHoldModel = new EmdHoldModel();
        $roleModel = new PartyRoleModel();
        $superAdminAuth = new SuperAdminAuthService();

        CLI::write('=== BR-04: TOTP setup and login ===', 'yellow');
        $admin = $partyModel->createParty('+919222801001');
        $partyModel->setMpinHash($admin['id'], password_hash('1234', PASSWORD_BCRYPT));
        $roleModel->grantRole($admin['id'], 'super_admin', null);

        $rejected = false;
        try {
            $superAdminAuth->login('+919222801001', '1234', '000000');
        } catch (\RuntimeException $e) {
            $rejected = str_contains($e->getMessage(), 'not been set up');
        }
        $this->assert($rejected, 'Login correctly blocked before TOTP is set up at all');

        $setup = $superAdminAuth->beginTotpSetup($admin['id']);
        $this->assert(strlen($setup['secret']) > 0, 'TOTP secret generated');
        $this->assert(str_starts_with($setup['provisioningUri'], 'otpauth://totp/'), 'Valid otpauth:// provisioning URI generated');

        $wrongConfirm = $superAdminAuth->confirmTotpSetup($admin['id'], '000000');
        $this->assert($wrongConfirm === false, 'Wrong code correctly rejected during setup confirmation');

        $validCode = $this->computeTotpCode($setup['secret']);
        $confirmed = $superAdminAuth->confirmTotpSetup($admin['id'], $validCode);
        $this->assert($confirmed === true, 'Correct code confirms TOTP setup');

        $wrongMpin = false;
        try {
            $superAdminAuth->login('+919222801001', '9999', $validCode);
        } catch (\RuntimeException $e) {
            $wrongMpin = str_contains($e->getMessage(), 'Incorrect mPIN');
        }
        $this->assert($wrongMpin, 'Wrong mPIN correctly rejected even with a valid TOTP code');

        $loggedIn = $superAdminAuth->login('+919222801001', '1234', $validCode);
        $this->assert($loggedIn['id'] === $admin['id'], 'Full login succeeds with correct mPIN + correct TOTP code');

        $nonAdmin = $partyModel->createParty('+919222801002');
        $partyModel->setMpinHash($nonAdmin['id'], password_hash('5555', PASSWORD_BCRYPT));
        $notSuperAdmin = false;
        try {
            $superAdminAuth->login('+919222801002', '5555', '123456');
        } catch (\RuntimeException $e) {
            $notSuperAdmin = str_contains($e->getMessage(), 'Super Admin access');
        }
        $this->assert($notSuperAdmin, 'A regular party without the super_admin role is correctly blocked, regardless of TOTP');

        CLI::write("\n=== BR-21: Inspector cannot bid on their own inspected listing ===", 'yellow');
        $tenant = $tenantModel->createTenant(['name' => 'Tier3 Test Tenant', 'tenant_class' => 'general', 'subdomain' => 'tier3test']);
        $seller = $partyModel->createParty('+919222801003');
        $inspector = $partyModel->createParty('+919222801004');
        $listing = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Machinery', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600017',
            'inspector_party_id' => $inspector['id'],
        ]);
        $saleEvent = $saleEventModel->createSaleEvent([
            'listing_id' => $listing['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-TIER3-001',
            'sale_format' => 'easy', 'reserve_value' => 40000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent['id'], $inspector['id'], 'van', 4000);

        $bidding = new BiddingService();
        $inspectorBlocked = false;
        try {
            $bidding->placeBid($saleEvent['id'], $inspector['id'], 45000);
        } catch (\RuntimeException $e) {
            $inspectorBlocked = str_contains($e->getMessage(), 'BR-21');
        }
        $this->assert($inspectorBlocked, 'The listing\'s own assigned inspector is blocked from bidding on it');

        CLI::write("\n=== BR-22: Tenant Admin cannot bid on their own tenant's listings ===", 'yellow');
        $tenantAdmin = $partyModel->createParty('+919222801005');
        $roleModel->promoteTenantAdmin($tenantAdmin['id'], $tenant['id']);
        $emdHoldModel->createHold($saleEvent['id'], $tenantAdmin['id'], 'van', 4000);

        $tenantAdminBlocked = false;
        try {
            $bidding->placeBid($saleEvent['id'], $tenantAdmin['id'], 45000);
        } catch (\RuntimeException $e) {
            $tenantAdminBlocked = str_contains($e->getMessage(), 'BR-22');
        }
        $this->assert($tenantAdminBlocked, 'The tenant\'s own Tenant Admin is blocked from bidding on its listings');

        CLI::write("\n=== A genuinely unrelated buyer is NOT blocked ===", 'yellow');
        $realBuyer = $partyModel->createParty('+919222801006');
        $emdHoldModel->createHold($saleEvent['id'], $realBuyer['id'], 'van', 4000);
        $realBid = $bidding->placeBid($saleEvent['id'], $realBuyer['id'], 45000);
        $this->assert((float) $realBid['amount'] === 45000.0, 'A real, unrelated buyer bids successfully — the check isn\'t overly broad');

        CLI::write("\n=== Same block applies to Buy-Now offers, not just Easy bidding ===", 'yellow');
        $listing2 = $listingModel->createListing([
            'tenant_id' => $tenant['id'], 'seller_party_id' => $seller['id'],
            'physical_condition' => 'Used', 'category' => 'Electronics', 'quantity' => 1,
            'quantity_basis' => 'unit', 'yard_location_address' => 'Test Yard', 'yard_location_pin' => '600018',
        ]);
        $saleEvent2 = $saleEventModel->createSaleEvent([
            'listing_id' => $listing2['id'], 'tenant_id' => $tenant['id'], 'ern' => 'TEST-TIER3-002',
            'sale_format' => 'buy_now', 'expected_value' => 50000, 'status' => 'active',
        ]);
        $emdHoldModel->createHold($saleEvent2['id'], $tenantAdmin['id'], 'van', 5000);
        $offers = new OfferService();
        $offerBlocked = false;
        try {
            $offers->submitOffer($saleEvent2['id'], $tenantAdmin['id'], 48000);
        } catch (\RuntimeException $e) {
            $offerBlocked = str_contains($e->getMessage(), 'BR-22');
        }
        $this->assert($offerBlocked, 'The conflict-of-interest block also applies to Buy-Now offer submission, not just Easy bidding');

        CLI::write("\n" . ($this->fail === 0 ? "🎉 ALL {$this->pass} ASSERTIONS PASSED" : "❌ {$this->fail} FAILURES, {$this->pass} passed"), $this->fail === 0 ? 'green' : 'red');
    }

    private function computeTotpCode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($secret) as $c) {
            $pos = strpos($alphabet, $c);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $b) {
            if (strlen($b) === 8) $bytes .= chr(bindec($b));
        }
        $timeStep = (int) floor(time() / 30);
        $time = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $time, $bytes, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24 | (ord($hash[$offset + 1]) & 0xFF) << 16 | (ord($hash[$offset + 2]) & 0xFF) << 8 | (ord($hash[$offset + 3]) & 0xFF)) % 1000000;
        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
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
