<?php

namespace IndieSystems\LoadGuard\Middleware;

use IndieSystems\LoadGuard\Events\JobThrottled;

class ThrottleWhenOverloaded
{
    public function handle($job, $next)
    {
        $priority = $job->loadGuardPriority ?? config('load-guard.job.default_priority', 'normal');
        $manager = app('load-guard');

        if (!$manager->canAcceptWork($priority)) {
            $delay = $job->loadGuardDelay ?? config('load-guard.job.default_delay', 60);
            $job->release($delay);

            if (config('load-guard.events.enabled', true)) {
                event(new JobThrottled($job, $delay, $manager->getMetrics()));
            }

            return;
        }

        $next($job);
    }
}
