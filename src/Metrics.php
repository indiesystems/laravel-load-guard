<?php

namespace IndieSystems\LoadGuard;

use Carbon\Carbon;

class Metrics
{
    public readonly float $cpu_load;
    public readonly int $cpu_cores;
    public readonly float $cpu_percent;
    public readonly float $memory_total_mb;
    public readonly float $memory_available_mb;
    public readonly float $memory_used_mb;
    public readonly float $memory_percent;
    public readonly float $swap_total_mb;
    public readonly float $swap_used_mb;
    public readonly Carbon $timestamp;

    public function __construct(
        float $cpu_load,
        int $cpu_cores,
        float $memory_total_mb,
        float $memory_available_mb,
        float $swap_total_mb,
        float $swap_used_mb
    ) {
        $this->cpu_load = $cpu_load;
        $this->cpu_cores = max(1, $cpu_cores);
        $this->cpu_percent = round(($this->cpu_load / $this->cpu_cores) * 100, 1);
        $this->memory_total_mb = $memory_total_mb;
        $this->memory_available_mb = $memory_available_mb;
        $this->memory_used_mb = max(0, round($memory_total_mb - $memory_available_mb, 1));
        $this->memory_percent = $memory_total_mb > 0
            ? round(($this->memory_used_mb / $memory_total_mb) * 100, 1)
            : 0.0;
        $this->swap_total_mb = $swap_total_mb;
        $this->swap_used_mb = max(0, $swap_used_mb);
        $this->timestamp = Carbon::now();
    }
}
