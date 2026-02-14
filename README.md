# Laravel Load Guard

A Laravel package that prevents server overload by monitoring CPU, memory, and swap usage, then automatically throttling jobs and HTTP requests when capacity limits are reached. Acts as a circuit breaker for server resources.

Designed for single-server / simple deployments (VPS, Forge, Docker) — not Kubernetes.

## Features

- Real-time CPU load, memory, and swap monitoring via `/proc` filesystem
- Configurable thresholds with priority levels (critical/normal/low)
- HTTP middleware to reject requests with 503 when overloaded
- Job middleware to delay queued jobs when server is under pressure
- Health check endpoint for load balancers (returns 200 or 503)
- Cooldown period to prevent state oscillation
- Events fired on state transitions (overload detected / recovered)
- Artisan commands for status checks and live monitoring

## Installation

```bash
composer require indiesystems/laravel-load-guard
php artisan load-guard:install
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=load-guard-config
```

Key settings in `config/load-guard.php`:

```php
'enabled' => env('LOAD_GUARD_ENABLED', true),
'reader'  => env('LOAD_GUARD_READER', 'auto'),  // auto|native|null
'cache_ttl' => 5,    // seconds between /proc reads
'cooldown'  => 30,   // seconds to stay "overloaded" after recovery

'thresholds' => [
    'cpu'    => 75,   // percentage (load / cores * 100)
    'memory' => 80,   // percentage
    'swap'   => 100,  // MB
],

'priorities' => [
    'critical' => ['cpu' => 95, 'memory' => 95, 'swap' => 500],
    'normal'   => null,  // uses default thresholds
    'low'      => ['cpu' => 60, 'memory' => 70, 'swap' => 50],
],
```

## Usage

### Health Check Endpoint

Enabled by default at `/load-guard/health`. Returns JSON with 200 (healthy) or 503 (overloaded):

```json
{
    "status": "healthy",
    "can_accept_work": true,
    "cpu": {"load": 2.1, "cores": 4, "percent": 52.5, "threshold": 75},
    "memory": {"total_mb": 8192, "available_mb": 3200, "used_mb": 4992, "percent": 60.9, "threshold": 80},
    "swap": {"total_mb": 2048, "used_mb": 0, "threshold": 100}
}
```

### HTTP Middleware

Reject incoming HTTP requests when the server is overloaded:

```php
// In routes
Route::middleware('load-guard.reject')->group(function () {
    Route::get('/api/heavy', [HeavyController::class, 'index']);
});
```

Returns 503 with `Retry-After` header when thresholds are exceeded.

### Job Middleware

Throttle queued jobs by releasing them back to the queue with a delay:

```php
use IndieSystems\LoadGuard\Middleware\ThrottleWhenOverloaded;

class ProcessReport implements ShouldQueue
{
    public function middleware(): array
    {
        return [new ThrottleWhenOverloaded];
    }

    // Optional: customize priority and delay
    public string $loadGuardPriority = 'low';
    public int $loadGuardDelay = 120;
}
```

### Facade

```php
use IndieSystems\LoadGuard\Facades\LoadGuard;

if (LoadGuard::canAcceptWork('normal')) {
    // safe to proceed
}

$metrics = LoadGuard::getMetrics();
// $metrics->cpu_percent, $metrics->memory_percent, $metrics->swap_used_mb
```

### Artisan Commands

```bash
# Show current server metrics
php artisan load-guard:status

# Live monitor (refreshes every 2s)
php artisan load-guard:watch
```

### Events

When events are enabled (default), these are fired on state transitions:

- `OverloadDetected` — server crossed a threshold
- `LoadRecovered` — server returned to healthy state
- `JobThrottled` — a queued job was delayed due to load

## Metrics Reader

The package reads server metrics from the Linux `/proc` filesystem:

- **NativeReader** — parses `/proc/meminfo`, `/proc/cpuinfo`, and `sys_getloadavg()`. Used automatically on Linux.
- **NullReader** — returns safe defaults (0% CPU, 50% memory, 0 swap). Used on macOS/Windows or when explicitly configured.

Set `LOAD_GUARD_READER=null` in `.env` to disable real monitoring (development).

## File Structure

```
src/
├── Providers/LoadGuardServiceProvider.php
├── Facades/LoadGuard.php
├── LoadGuardManager.php
├── Metrics.php
├── Readers/
│   ├── ReaderInterface.php
│   ├── NativeReader.php
│   └── NullReader.php
├── Middleware/
│   ├── ThrottleWhenOverloaded.php
│   └── RejectWhenOverloaded.php
├── Events/
│   ├── OverloadDetected.php
│   ├── LoadRecovered.php
│   └── JobThrottled.php
├── Console/
│   ├── StatusCommand.php
│   ├── WatchCommand.php
│   └── InstallCommand.php
├── config/load-guard.php
└── routes/health.php
```

## Requirements

- PHP >= 8.1
- Laravel 9, 10, or 11
- Linux with `/proc` filesystem (for real metrics)

## License

MIT
