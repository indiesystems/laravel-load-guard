<?php

namespace IndieSystems\LoadGuard\Readers;

use IndieSystems\LoadGuard\Metrics;

interface ReaderInterface
{
    public function read(): Metrics;
}
