<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\SchedulerService;

// Runs every timer this platform depends on, in one pass. Intended to be
// called by a real cron entry every minute (see SETUP.md) — this file
// itself is NOT a scheduler daemon, it's what a real scheduler calls.
class RunScheduler extends BaseCommand
{
    protected $group       = 'Scheduler';
    protected $name        = 'run:scheduler';
    protected $description = 'Processes every time-based platform trigger — grace windows, Express bidding close, offer lapse, settlement stall-flagging.';

    public function run(array $params)
    {
        $scheduler = new SchedulerService();
        $results = $scheduler->runAll();

        CLI::write('Grace periods frozen: ' . count($results['gracePeriodsProcessed']), 'green');
        CLI::write('Express auctions cascaded: ' . count($results['expressBiddingClosed']), 'green');
        CLI::write('Easy auctions closed: ' . count($results['easyAuctionsClosed']), 'green');
        CLI::write('Stale offers lapsed: ' . count($results['staleOffersLapsed']), 'green');
        CLI::write('Settlements flagged stalled: ' . count($results['settlementsFlaggedStalled']), 'green');
    }
}
