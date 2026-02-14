<?php

namespace IndieSystems\LoadGuard\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'load-guard:install';
    protected $description = 'Install the Load Guard package';

    public function handle(): int
    {
        $this->info('Installing Load Guard...');
        $this->newLine();

        $this->publishConfig();
        $this->checkEnvironment();
        $this->testReader();
        $this->showPostInstall();

        $this->newLine();
        $this->info('Load Guard installed successfully!');

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $this->components->task('Publishing config', function () {
            $this->callSilently('vendor:publish', ['--tag' => 'load-guard-config']);
            return true;
        });
    }

    protected function checkEnvironment(): void
    {
        $this->components->task('Checking /proc filesystem', function () {
            return is_dir('/proc') && is_readable('/proc/meminfo');
        });

        if (!is_dir('/proc')) {
            $this->warn('  /proc not found — NullReader will be used (returns healthy defaults).');
            $this->line('  This is normal on macOS/Windows. On Linux, ensure /proc is mounted.');
        }
    }

    protected function testReader(): void
    {
        $this->components->task('Testing metrics reader', function () {
            try {
                $metrics = app('load-guard')->getMetrics();
                return $metrics->cpu_cores > 0;
            } catch (\Exception $e) {
                return false;
            }
        });

        try {
            $metrics = app('load-guard')->getMetrics();
            $this->line(sprintf(
                '  CPU: %.1f/%d cores (%.0f%%) | Memory: %.0f/%.0fMB (%.0f%%) | Swap: %.0fMB',
                $metrics->cpu_load,
                $metrics->cpu_cores,
                $metrics->cpu_percent,
                $metrics->memory_used_mb,
                $metrics->memory_total_mb,
                $metrics->memory_percent,
                $metrics->swap_used_mb
            ));
        } catch (\Exception $e) {
            // Silently skip if reader fails
        }
    }

    protected function showPostInstall(): void
    {
        $this->newLine();
        $this->components->info('Post-install:');
        $this->newLine();

        $this->line('  1. (Optional) Add HTTP middleware to reject requests when overloaded:');
        $this->line("     Route::middleware('load-guard.reject')->group(...)");
        $this->newLine();

        $this->line('  2. (Optional) Add job middleware to throttle queued jobs:');
        $this->line('     public function middleware() { return [new ThrottleWhenOverloaded]; }');
        $this->newLine();

        $this->line('  3. Health endpoint: /load-guard/health (200 or 503)');
        $this->newLine();

        $this->line('  4. Monitor commands:');
        $this->line('     php artisan load-guard:status   — Current metrics');
        $this->line('     php artisan load-guard:watch    — Live monitor');
    }
}
