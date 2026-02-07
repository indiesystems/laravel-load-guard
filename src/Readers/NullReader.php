<?php

namespace IndieSystems\LoadGuard\Readers;

use IndieSystems\LoadGuard\Metrics;

class NullReader implements ReaderInterface
{
    public function read(): Metrics
    {
        return new Metrics(
            cpu_load: 0.0,
            cpu_cores: 1,
            memory_total_mb: 1024.0,
            memory_available_mb: 512.0,
            swap_total_mb: 0.0,
            swap_used_mb: 0.0
        );
    }
}
