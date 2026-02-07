<?php

namespace IndieSystems\LoadGuard\Readers;

use IndieSystems\LoadGuard\Metrics;

class NativeReader implements ReaderInterface
{
    protected static ?int $cachedCoreCount = null;

    public function read(): Metrics
    {
        $loadAvg = sys_getloadavg();
        $cpuLoad = $loadAvg[0] ?? 0.0;
        $cpuCores = $this->getCoreCount();

        $memInfo = $this->parseMemInfo();

        return new Metrics(
            cpu_load: $cpuLoad,
            cpu_cores: $cpuCores,
            memory_total_mb: $memInfo['mem_total'],
            memory_available_mb: $memInfo['mem_available'],
            swap_total_mb: $memInfo['swap_total'],
            swap_used_mb: $memInfo['swap_used']
        );
    }

    protected function getCoreCount(): int
    {
        if (static::$cachedCoreCount !== null) {
            return static::$cachedCoreCount;
        }

        $cores = 1;

        if (is_readable('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');
            if ($content !== false) {
                $cores = substr_count($content, 'processor');
                $cores = max(1, $cores);
            }
        }

        static::$cachedCoreCount = $cores;

        return $cores;
    }

    protected function parseMemInfo(): array
    {
        $result = [
            'mem_total' => 0.0,
            'mem_available' => 0.0,
            'swap_total' => 0.0,
            'swap_used' => 0.0,
        ];

        if (!is_readable('/proc/meminfo')) {
            return $result;
        }

        $content = file_get_contents('/proc/meminfo');
        if ($content === false) {
            return $result;
        }

        $values = [];
        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $matches)) {
                $values[$matches[1]] = (float) $matches[2];
            }
        }

        $result['mem_total'] = round(($values['MemTotal'] ?? 0) / 1024, 1);
        $result['mem_available'] = round(($values['MemAvailable'] ?? 0) / 1024, 1);
        $result['swap_total'] = round(($values['SwapTotal'] ?? 0) / 1024, 1);
        $swapFree = round(($values['SwapFree'] ?? 0) / 1024, 1);
        $result['swap_used'] = round($result['swap_total'] - $swapFree, 1);

        return $result;
    }
}
