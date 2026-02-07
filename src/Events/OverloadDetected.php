<?php

namespace IndieSystems\LoadGuard\Events;

use IndieSystems\LoadGuard\Metrics;

class OverloadDetected
{
    public Metrics $metrics;
    public array $exceededThresholds;

    public function __construct(Metrics $metrics, array $exceededThresholds)
    {
        $this->metrics = $metrics;
        $this->exceededThresholds = $exceededThresholds;
    }
}
