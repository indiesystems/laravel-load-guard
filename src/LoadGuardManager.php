<?php

namespace IndieSystems\LoadGuard;

use Illuminate\Support\Facades\Cache;
use IndieSystems\LoadGuard\Events\LoadRecovered;
use IndieSystems\LoadGuard\Events\OverloadDetected;
use IndieSystems\LoadGuard\Readers\ReaderInterface;

class LoadGuardManager
{
    protected ReaderInterface $reader;

    public function __construct(ReaderInterface $reader)
    {
        $this->reader = $reader;
    }

    public function canAcceptWork(string $priority = 'normal'): bool
    {
        if (!config('load-guard.enabled', true)) {
            return true;
        }

        $metrics = $this->getMetrics();
        $thresholds = $this->resolveThresholds($priority);

        $exceeded = $this->checkThresholds($metrics, $thresholds);
        $isOverloaded = !empty($exceeded);

        if ($isOverloaded) {
            Cache::put('load_guard.last_overload', now()->timestamp, config('load-guard.cooldown', 30) * 2);
        }

        // Cooldown check: even if metrics are healthy, stay in overloaded state until cooldown expires
        if (!$isOverloaded) {
            $lastOverload = Cache::get('load_guard.last_overload');
            if ($lastOverload) {
                $cooldown = config('load-guard.cooldown', 30);
                $elapsed = now()->timestamp - $lastOverload;
                if ($elapsed < $cooldown) {
                    $isOverloaded = true;
                }
            }
        }

        // State transition events
        $this->handleStateTransition($isOverloaded, $metrics, $exceeded);

        return !$isOverloaded;
    }

    public function getMetrics(): Metrics
    {
        $ttl = config('load-guard.cache_ttl', 5);

        return Cache::remember('load_guard.metrics', $ttl, function () {
            return $this->reader->read();
        });
    }

    public function isOverloaded(): bool
    {
        return !$this->canAcceptWork();
    }

    public function getStatus(): array
    {
        $metrics = $this->getMetrics();
        $thresholds = $this->resolveThresholds('normal');
        $exceeded = $this->checkThresholds($metrics, $thresholds);
        $isOverloaded = !empty($exceeded);

        // Check cooldown
        $cooldownActive = false;
        $cooldownRemaining = 0;
        $lastOverload = Cache::get('load_guard.last_overload');
        if ($lastOverload) {
            $cooldown = config('load-guard.cooldown', 30);
            $elapsed = now()->timestamp - $lastOverload;
            if ($elapsed < $cooldown) {
                $cooldownActive = true;
                $cooldownRemaining = $cooldown - $elapsed;
            }
        }

        $canAcceptWork = !$isOverloaded && !$cooldownActive;

        if ($isOverloaded) {
            $status = 'overloaded';
        } elseif ($cooldownActive) {
            $status = 'cooldown';
        } else {
            $status = 'healthy';
        }

        return [
            'status' => $status,
            'can_accept_work' => $canAcceptWork,
            'cpu' => [
                'load' => $metrics->cpu_load,
                'cores' => $metrics->cpu_cores,
                'percent' => $metrics->cpu_percent,
                'threshold' => $thresholds['cpu'],
            ],
            'memory' => [
                'total_mb' => $metrics->memory_total_mb,
                'available_mb' => $metrics->memory_available_mb,
                'used_mb' => $metrics->memory_used_mb,
                'percent' => $metrics->memory_percent,
                'threshold' => $thresholds['memory'],
            ],
            'swap' => [
                'total_mb' => $metrics->swap_total_mb,
                'used_mb' => $metrics->swap_used_mb,
                'threshold' => $thresholds['swap'],
            ],
            'cooldown' => [
                'active' => $cooldownActive,
                'remaining_seconds' => $cooldownRemaining,
            ],
            'reader' => $this->getReaderName(),
            'timestamp' => $metrics->timestamp->toIso8601String(),
        ];
    }

    protected function resolveThresholds(string $priority): array
    {
        $priorities = config('load-guard.priorities', []);

        if (isset($priorities[$priority]) && is_array($priorities[$priority])) {
            return $priorities[$priority];
        }

        // Fall back to main thresholds
        return [
            'cpu' => config('load-guard.thresholds.cpu', 75),
            'memory' => config('load-guard.thresholds.memory', 80),
            'swap' => config('load-guard.thresholds.swap', 100),
        ];
    }

    protected function checkThresholds(Metrics $metrics, array $thresholds): array
    {
        $exceeded = [];

        if ($metrics->cpu_percent >= $thresholds['cpu']) {
            $exceeded['cpu'] = [
                'current' => $metrics->cpu_percent,
                'threshold' => $thresholds['cpu'],
            ];
        }

        if ($metrics->memory_percent >= $thresholds['memory']) {
            $exceeded['memory'] = [
                'current' => $metrics->memory_percent,
                'threshold' => $thresholds['memory'],
            ];
        }

        if ($metrics->swap_used_mb >= $thresholds['swap']) {
            $exceeded['swap'] = [
                'current' => $metrics->swap_used_mb,
                'threshold' => $thresholds['swap'],
            ];
        }

        return $exceeded;
    }

    protected function handleStateTransition(bool $isOverloaded, Metrics $metrics, array $exceeded): void
    {
        if (!config('load-guard.events.enabled', true)) {
            return;
        }

        $previousState = Cache::get('load_guard.state', 'healthy');
        $currentState = $isOverloaded ? 'overloaded' : 'healthy';

        if ($previousState === $currentState) {
            return;
        }

        Cache::put('load_guard.state', $currentState, 3600);

        if ($currentState === 'overloaded') {
            Cache::put('load_guard.overload_started', now()->timestamp, 3600);
            event(new OverloadDetected($metrics, $exceeded));
        } else {
            $startedAt = Cache::get('load_guard.overload_started', now()->timestamp);
            $duration = now()->timestamp - $startedAt;
            Cache::forget('load_guard.overload_started');
            event(new LoadRecovered($metrics, $duration));
        }
    }

    protected function getReaderName(): string
    {
        $class = get_class($this->reader);
        $parts = explode('\\', $class);

        return str_replace('Reader', '', end($parts));
    }
}
