<?php

namespace IndieSystems\LoadGuard\Console;

use Illuminate\Console\Command;
use IndieSystems\LoadGuard\Console\Concerns\RendersStatusTable;

class StatusCommand extends Command
{
    use RendersStatusTable;

    protected $signature = 'load-guard:status';
    protected $description = 'Display current server load metrics and guard status';

    public function handle(): int
    {
        $manager = app('load-guard');
        $status = $manager->getStatus();

        $this->renderStatusTable($status);

        return self::SUCCESS;
    }
}
