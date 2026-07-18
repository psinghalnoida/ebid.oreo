<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PartyModel;
use App\Libraries\RatingService;

class TestRating extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:rating';
    protected $description = 'Runs the rating engine against real data.';

    private int $pass = 0;
    private int $fail = 0;

    public function run(array $params)
    {
        $partyModel = new PartyModel();
        $ratingService = new RatingService();

        CLI::write('=== Setup ===', 'yellow');
        $tenantAdmin = $partyModel->createParty('+919111211101');
        $superAdmin = $partyModel->createParty('+919111211102');
        $buyer = $partyModel->createParty('+919111211103');
        $this->assert((float) $buyer['star_rating'] === 3.0, 'BR-35: new party defaults to exactly 3.0 star_rating');

        CLI::write("\n=== BR-36: Automatic upgrade (no approval needed) ===", 'yellow');
        $upgradeEvent = $ratingService->applyUpgrade($buyer['id'], 'star_rating', 0.3, 'Early settlement');
        $this->assert($upgradeEvent['status'] === 'applied', 'Upgrade applied immediately, no approval gate');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 3.3, 'Rating is now 3.3 (was 3.0, upgrade of 0.3)');

        CLI::write("\n=== BR-36: Downgrade to 2.5 (mid-range) — Tenant Admin approval only ===", 'yellow');
        $downgrade1 = $ratingService->initiateDowngrade($buyer['id'], 'star_rating', 0.8, 'Late payment');
        $this->assert($downgrade1['requiresDualApproval'] === false, 'Downgrade to 2.5 does NOT require dual approval');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 3.3, 'Rating UNCHANGED until approval lands (BR-36 gate holds)');

        $approval1 = $ratingService->approveDowngrade($downgrade1['id'], $tenantAdmin['id'], 'tenant_admin');
        $this->assert($approval1['applied'] === true, 'Applied immediately after single Tenant Admin approval');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 2.5, 'Rating now 2.5 after approved downgrade');

        CLI::write("\n=== BR-36: Downgrade to <=2.0 — DUAL approval required ===", 'yellow');
        $downgrade2 = $ratingService->initiateDowngrade($buyer['id'], 'star_rating', 0.6, 'Repeated frivolous disputes');
        $this->assert($downgrade2['requiresDualApproval'] === true, 'Downgrade to 1.9 requires dual approval');

        $tenantOnly = $ratingService->approveDowngrade($downgrade2['id'], $tenantAdmin['id'], 'tenant_admin');
        $this->assert($tenantOnly['applied'] === false, 'NOT applied after only Tenant Admin approval');
        $this->assert($tenantOnly['waitingOn'] === 'super_admin', 'Correctly waiting on Super Admin');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 2.5, 'Rating still 2.5 — single approval insufficient for dual-gate case');

        $dualApproved = $ratingService->approveDowngrade($downgrade2['id'], $superAdmin['id'], 'super_admin');
        $this->assert($dualApproved['applied'] === true, 'Applied once BOTH Tenant Admin and Super Admin approved');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 1.9, 'Rating now 1.9 after dual-approved downgrade');

        CLI::write("\n=== BR-38: Crawl-Back triggered ===", 'yellow');
        $this->assert($current['crawl_back_active_buyer'] === 't' || $current['crawl_back_active_buyer'] === true, 'Crawl-Back automatically activated');
        $this->assert((int) $current['crawl_back_clean_required_buyer'] === 3, 'First offence requires 3 clean transactions');
        $this->assert((int) $current['offence_count_buyer'] === 1, 'Offence count incremented to 1');

        CLI::write("\n=== BR-38: Completing Crawl-Back ===", 'yellow');
        $ratingService->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $ratingService->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $current = $partyModel->find($buyer['id']);
        $this->assert(
            $current['crawl_back_active_buyer'] === 't' || $current['crawl_back_active_buyer'] === true,
            'Still active after 2 of 3 required clean transactions'
        );

        $completion = $ratingService->recordCleanTransactionForCrawlBack($buyer['id'], 'star_rating');
        $this->assert($completion['crawlBackCompleted'] === true, '3rd clean transaction completes Crawl-Back');
        $current = $partyModel->find($buyer['id']);
        $this->assert((float) $current['star_rating'] === 3.0, 'BR-38: rating restored to exactly 3.0 stars');
        $this->assert(
            $current['crawl_back_active_buyer'] === 'f' || $current['crawl_back_active_buyer'] === false,
            'Crawl-Back deactivated after restoration'
        );

        CLI::write("\n=== BR-39: Forced-neutral stall resolution, 5-strike pattern ===", 'yellow');
        $seller = $partyModel->createParty('+919111211104');
        for ($i = 1; $i <= 4; $i++) {
            $result = $ratingService->applyForcedNeutral($seller['id'], 'seller_star_rating', null, "Stall #{$i}");
            $this->assert($result['patternTriggered'] === false, "Forced-neutral #{$i}: no pattern event yet ({$result['strikeCount']}/5)");
        }
        $fifth = $ratingService->applyForcedNeutral($seller['id'], 'seller_star_rating', null, 'Stall #5');
        $this->assert($fifth['strikeCount'] === 5, '5th forced-neutral instance recorded');
        $this->assert($fifth['patternTriggered'] === true, 'BR-39: 5th instance triggers a real rating-damaging event');
        $this->assert($fifth['pendingDowngradeEvent']['requiresDualApproval'] === false, 'Pattern-triggered downgrade goes through normal BR-36 gate');

        $sellerAfter = $partyModel->find($seller['id']);
        $this->assert(
            (float) $sellerAfter['seller_star_rating'] === 3.0,
            'Seller rating still 3.0 — pattern-triggered downgrade is PENDING approval, not silently applied'
        );

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
