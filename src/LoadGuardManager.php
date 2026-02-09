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

        $state = $this->evaluate($priority);

        return $state['can_accept_work'];
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
        if (!config('load-guard.enabled', true)) {
            $metrics = $this->getMetrics();
            $thresholds = $this->resolveThresholds('normal');

            return [
                'status' => 'healthy',
                'can_accept_work' => true,
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
                    'active' => false,
                    'remaining_seconds' => 0,
                ],
                'reader' => $this->getReaderName(),
                'timestamp' => $metrics->timestamp->toIso8601String(),
            ];
        }

        $state = $this->evaluate('normal');

        return [
            'status' => $state['status'],
            'can_accept_work' => $state['can_accept_work'],
            'cpu' => [
                'load' => $state['metrics']->cpu_load,
                'cores' => $state['metrics']->cpu_cores,
                'percent' => $state['metrics']->cpu_percent,
                'threshold' => $state['thresholds']['cpu'],
            ],
            'memory' => [
                'total_mb' => $state['metrics']->memory_total_mb,
                'available_mb' => $state['metrics']->memory_available_mb,
                'used_mb' => $state['metrics']->memory_used_mb,
                'percent' => $state['metrics']->memory_percent,
                'threshold' => $state['thresholds']['memory'],
            ],
            'swap' => [
                'total_mb' => $state['metrics']->swap_total_mb,
                'used_mb' => $state['metrics']->swap_used_mb,
                'threshold' => $state['thresholds']['swap'],
            ],
            'cooldown' => [
                'active' => $state['cooldown_active'],
                'remaining_seconds' => $state['cooldown_remaining'],
            ],
            'reader' => $this->getReaderName(),
            'timestamp' => $state['metrics']->timestamp->toIso8601String(),
        ];
    }

    /**
     * Central state evaluation — single code path for all overload checks.
     * Handles threshold checks, cooldown, cache writes, and event firing.
     */
    protected function evaluate(string $priority): array
    {
        $metrics = $this->getMetrics();
        $thresholds = $this->resolveThresholds($priority);

        $exceeded = $this->checkThresholds($metrics, $thresholds);
        $thresholdsExceeded = !empty($exceeded);

        // If thresholds are currently exceeded, record the overload timestamp
        if ($thresholdsExceeded) {
            $cooldownSeconds = config('load-guard.cooldown', 30);
            Cache::put('load_guard.last_overload', now()->timestamp, $cooldownSeconds * 2);
        }

        // Cooldown check: even if metrics recovered, stay overloaded until cooldown expires
        $cooldownActive = false;
        $cooldownRemaining = 0;

        if (!$thresholdsExceeded) {
            $lastOverload = Cache::get('load_guard.last_overload');
            if ($lastOverload) {
                $cooldownSeconds = config('load-guard.cooldown', 30);
                $elapsed = now()->timestamp - $lastOverload;
                if ($elapsed < $cooldownSeconds) {
                    $cooldownActive = true;
                    $cooldownRemaining = $cooldownSeconds - $elapsed;
                }
            }
        }

        $isOverloaded = $thresholdsExceeded || $cooldownActive;

        // Determine display status
        if ($thresholdsExceeded) {
            $status = 'overloaded';
        } elseif ($cooldownActive) {
            $status = 'cooldown';
        } else {
            $status = 'healthy';
        }

        // Fire events on state transitions (only when thresholds actually exceeded,
        // not during cooldown, to avoid misleading events with empty exceeded list)
        $this->handleStateTransition($thresholdsExceeded, $metrics, $exceeded);

        return [
            'can_accept_work' => !$isOverloaded,
            'status' => $status,
            'metrics' => $metrics,
            'thresholds' => $thresholds,
            'exceeded' => $exceeded,
            'cooldown_active' => $cooldownActive,
            'cooldown_remaining' => $cooldownRemaining,
        ];
    }

    protected function resolveThresholds(string $priority): array
    {
        $priorities = config('load-guard.priorities', []);

        if (isset($priorities[$priority]) && is_array($priorities[$priority])) {
            return $priorities[$priority];
        }

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

    /**
     * Fire events only on actual state transitions (thresholds exceeded/recovered).
     * Uses $thresholdsExceeded (not cooldown-inflated overload) so events
     * always carry meaningful exceeded data.
     */
    protected function handleStateTransition(bool $thresholdsExceeded, Metrics $metrics, array $exceeded): void
    {
        if (!config('load-guard.events.enabled', true)) {
            return;
        }

        $previousState = Cache::get('load_guard.state', 'healthy');
        $currentState = $thresholdsExceeded ? 'overloaded' : 'healthy';

        if ($previousState === $currentState) {
            return;
        }

        // Use forever() instead of a fixed TTL so state doesn't silently expire
        // and cause duplicate transition events
        Cache::forever('load_guard.state', $currentState);

        if ($currentState === 'overloaded') {
            Cache::forever('load_guard.overload_started', now()->timestamp);
            event(new OverloadDetected($metrics, $exceeded));
        } else {
            $startedAt = Cache::pull('load_guard.overload_started', now()->timestamp);
            $duration = now()->timestamp - $startedAt;
            event(new LoadRecovered($metrics, $duration));
        }
    }

    protected function getReaderName(): string
    {
        $class = get_class($this->reader);
        $short = substr(strrchr($class, '\\'), 1);

        if (str_ends_with($short, 'Reader')) {
            return substr($short, 0, -6);
        }

        return $short;
    }
}
