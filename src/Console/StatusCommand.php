<?php

namespace IndieSystems\LoadGuard\Console;

use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'load-guard:status';
    protected $description = 'Display current server load metrics and guard status';

    public function handle(): int
    {
        $manager = app('load-guard');
        $status = $manager->getStatus();

        $this->renderTable($status);

        return self::SUCCESS;
    }

    protected function renderTable(array $status): void
    {
        $cpu = $status['cpu'];
        $memory = $status['memory'];
        $swap = $status['swap'];

        $rows = [
            [
                'CPU',
                round($cpu['load'], 1) . '/' . $cpu['cores'],
                $cpu['threshold'] . '%',
                $cpu['percent'] . '%',
                $cpu['percent'] >= $cpu['threshold'] ? '<fg=red>EXCEEDED</>' : '<fg=green>OK</>',
            ],
            [
                'Memory',
                round($memory['used_mb'] / 1024, 1) . '/' . round($memory['total_mb'] / 1024, 1) . 'G',
                $memory['threshold'] . '%',
                $memory['percent'] . '%',
                $memory['percent'] >= $memory['threshold'] ? '<fg=red>EXCEEDED</>' : '<fg=green>OK</>',
            ],
            [
                'Swap',
                round($swap['used_mb']) . ' MB',
                $swap['threshold'] . ' MB',
                round($swap['used_mb']) . ' MB',
                $swap['used_mb'] >= $swap['threshold'] ? '<fg=red>EXCEEDED</>' : '<fg=green>OK</>',
            ],
        ];

        $this->table(['Resource', 'Value', 'Limit', 'Current', 'Status'], $rows);

        $statusLabel = strtoupper($status['status']);
        $statusColor = $status['status'] === 'healthy' ? 'green' : 'red';
        $cooldownLabel = $status['cooldown']['active']
            ? "active ({$status['cooldown']['remaining_seconds']}s remaining)"
            : 'inactive';

        $this->line('');
        $this->line("Status: <fg={$statusColor}>{$statusLabel}</> | Reader: {$status['reader']} | Cooldown: {$cooldownLabel}");
    }
}
