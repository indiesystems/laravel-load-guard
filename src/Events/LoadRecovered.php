<?php

namespace IndieSystems\LoadGuard\Events;

use IndieSystems\LoadGuard\Metrics;

class LoadRecovered
{
    public Metrics $metrics;
    public int $overloadDurationSeconds;

    public function __construct(Metrics $metrics, int $overloadDurationSeconds)
    {
        $this->metrics = $metrics;
        $this->overloadDurationSeconds = $overloadDurationSeconds;
    }
}
