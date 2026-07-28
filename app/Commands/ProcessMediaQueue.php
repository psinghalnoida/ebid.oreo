<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\MediaQueueService;

// PR-09: drains the media upload job queue sequentially. Intended as a
// real cron entry (same deployment pattern as `php spark run:scheduler`),
// and also invoked automatically from SchedulerService::runAll() so the
// existing cron sweep drains it without a second, separate cron line.
class ProcessMediaQueue extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'process:media-queue';
    protected $description = 'Drains the PR-09 background media-processing queue sequentially.';

    public function run(array $params)
    {
        $results = (new MediaQueueService())->processAll();
        $done = count(array_filter($results, fn($r) => $r['outcome'] === 'done'));
        $failed = count(array_filter($results, fn($r) => $r['outcome'] === 'failed'));
        CLI::write("Media queue drained: {$done} processed, {$failed} failed, " . count($results) . ' total.', $failed > 0 ? 'yellow' : 'green');
    }
}
