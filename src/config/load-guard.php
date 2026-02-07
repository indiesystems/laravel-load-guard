<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/Disable Load Guard
    |--------------------------------------------------------------------------
    |
    | When disabled, all checks return "healthy" and no throttling occurs.
    |
    */
    'enabled' => env('LOAD_GUARD_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Metrics Reader
    |--------------------------------------------------------------------------
    |
    | "auto" will use NativeReader on Linux (when /proc exists) and fall back
    | to NullReader on other platforms. Set to "native" or "null" to force.
    |
    */
    'reader' => env('LOAD_GUARD_READER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | How long to cache metrics reads. Prevents excessive /proc reads.
    |
    */
    'cache_ttl' => env('LOAD_GUARD_CACHE_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Cooldown (seconds)
    |--------------------------------------------------------------------------
    |
    | After load recovers, stay in "overloaded" state for this many seconds
    | to prevent oscillation (rapid toggling between states).
    |
    */
    'cooldown' => env('LOAD_GUARD_COOLDOWN', 30),

    /*
    |--------------------------------------------------------------------------
    | Default Thresholds
    |--------------------------------------------------------------------------
    |
    | The default thresholds for "normal" priority work.
    | CPU and memory are percentages, swap is in MB.
    |
    */
    'thresholds' => [
        'cpu' => env('LOAD_GUARD_CPU_THRESHOLD', 75),
        'memory' => env('LOAD_GUARD_MEMORY_THRESHOLD', 80),
        'swap' => env('LOAD_GUARD_SWAP_THRESHOLD', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Levels
    |--------------------------------------------------------------------------
    |
    | Define custom thresholds per priority level.
    | Set to null to use the default thresholds above.
    |
    */
    'priorities' => [
        'critical' => ['cpu' => 95, 'memory' => 95, 'swap' => 500],
        'normal' => null,
        'low' => ['cpu' => 60, 'memory' => 70, 'swap' => 50],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Throttling Defaults
    |--------------------------------------------------------------------------
    */
    'job' => [
        'default_delay' => 60,
        'default_priority' => 'normal',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Middleware
    |--------------------------------------------------------------------------
    */
    'http' => [
        'retry_after' => 60,
        'exclude_paths' => ['load-guard/*', 'health'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Endpoint
    |--------------------------------------------------------------------------
    */
    'health_check' => [
        'enabled' => env('LOAD_GUARD_HEALTH_ENABLED', true),
        'path' => env('LOAD_GUARD_HEALTH_PATH', 'load-guard/health'),
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'enabled' => true,
    ],

];
