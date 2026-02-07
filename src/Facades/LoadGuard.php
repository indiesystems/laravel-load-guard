<?php

namespace IndieSystems\LoadGuard\Facades;

use Illuminate\Support\Facades\Facade;

class LoadGuard extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'load-guard';
    }
}
