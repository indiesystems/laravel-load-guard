<?php

namespace IndieSystems\LoadGuard\Events;

use IndieSystems\LoadGuard\Metrics;

class JobThrottled
{
    public object $job;
    public int $delay;
    public Metrics $metrics;

    public function __construct(object $job, int $delay, Metrics $metrics)
    {
        $this->job = $job;
        $this->delay = $delay;
        $this->metrics = $metrics;
    }
}
