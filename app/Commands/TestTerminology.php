<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestTerminology extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:terminology';
    protected $description = 'Proves BR-67 (Branded Terminology Layer) is real: the tsx_term() map is complete, and the underlying technical names it never touches are still intact.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        helper('terminology');

        CLI::write('=== BR-67: the 7-row mapping table, long form ===', 'yellow');
        $this->assert(tsx_term('Tenant') === 'TradeSphereX', 'Tenant -> TradeSphereX');
        $this->assert(tsx_term('Tenant Admin') === 'TSX Master', 'Tenant Admin -> TSX Master');
        $this->assert(tsx_term('Seller') === 'Market Maker', 'Seller -> Market Maker');
        $this->assert(tsx_term('Buyer') === 'Trader', 'Buyer -> Trader');
        $this->assert(tsx_term('Super Admin') === 'Custodian', 'Super Admin -> Custodian');
        $this->assert(tsx_term('Listing') === 'Lot', 'Listing -> Lot');
        $this->assert(tsx_term('Sale Event') === 'Trading Session', 'Sale Event -> Trading Session');

        CLI::write("\n=== Short form ===", 'yellow');
        $this->assert(tsx_term('Tenant', true) === 'TSX', 'Tenant short -> TSX');
        $this->assert(tsx_term('Tenant Admin', true) === 'TSXM', 'Tenant Admin short -> TSXM');
        $this->assert(tsx_term('Seller', true) === 'MM', 'Seller short -> MM');
        $this->assert(tsx_term('Buyer', true) === 'TRD', 'Buyer short -> TRD');
        $this->assert(tsx_term('Super Admin', true) === 'CUS', 'Super Admin short -> CUS');
        $this->assert(tsx_term('Listing', true) === 'LOT', 'Listing short -> LOT');
        $this->assert(tsx_term('Sale Event', true) === 'Trading Session', 'Sale Event has no distinct short form');

        CLI::write("\n=== Plural form ===", 'yellow');
        $this->assert(tsx_term('Seller', false, true) === 'Market Makers', 'Seller plural -> Market Makers');
        $this->assert(tsx_term('Listing', false, true) === 'Lots', 'Listing plural -> Lots');
        $this->assert(tsx_term('Buyer', true, true) === 'TRDs', 'Buyer short+plural -> TRDs');

        CLI::write("\n=== Presentation-only: unmapped/unknown input passes through unchanged ===", 'yellow');
        $this->assert(tsx_term('eBid Hub') === 'eBid Hub', "The platform's own name is not part of this mapping and is untouched");
        $this->assert(tsx_term('Party') === 'Party', 'A term outside the 7-row table is returned unchanged, not blanked or errored');

        CLI::write("\n=== BR-67 does not rename the data model: the technical role/entity names still work as real identifiers ===", 'yellow');
        $tenantModel = new \App\Models\TenantModel();
        $this->assert(in_array('coco_starter', \App\Models\TenantModel::SUBSCRIPTION_TIERS, true), 'TenantModel still keyed on real subscription_tier values, unrenamed');
        $this->assert(!\App\Models\TenantModel::hasApiAccess('coco_starter'), 'hasApiAccess() still keys off the real coco_starter tier value');
        $reflection = new \ReflectionClass($tenantModel);
        $this->assert($reflection->getProperty('table')->getDefaultValue() === 'tenant', "The DB table is still literally 'tenant' — BR-67 is presentation-only");

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
