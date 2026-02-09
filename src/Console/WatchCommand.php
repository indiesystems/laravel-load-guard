<?php

namespace IndieSystems\LoadGuard\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use IndieSystems\LoadGuard\Console\Concerns\RendersStatusTable;

class WatchCommand extends Command
{
    use RendersStatusTable;

    protected $signature = 'load-guard:watch {--interval=2 : Refresh interval in seconds}';
    protected $description = 'Live-watch server load metrics (refreshes every N seconds)';

    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        $interval = max(1, $interval);

        while (true) {
            // Clear cached metrics to get fresh reads
            Cache::forget('load_guard.metrics');

            $manager = app('load-guard');
            $status = $manager->getStatus();

            // Clear screen and render
            $this->output->write("\033[H\033[2J");
            $this->info("Load Guard Watch (refresh every {$interval}s) — Press Ctrl+C to stop");
            $this->line('');
            $this->renderStatusTable($status);
            $this->line('');
            $this->line('<fg=gray>Last updated: ' . $status['timestamp'] . '</>');

            sleep($interval);
        }

        return self::SUCCESS; // @codeCoverageIgnore
    }
}
