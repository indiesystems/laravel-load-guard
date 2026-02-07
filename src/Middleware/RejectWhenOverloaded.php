<?php

namespace IndieSystems\LoadGuard\Middleware;

use Closure;
use Illuminate\Http\Request;

class RejectWhenOverloaded
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('load-guard.enabled', true)) {
            return $next($request);
        }

        foreach (config('load-guard.http.exclude_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        if (app('load-guard')->isOverloaded()) {
            $retryAfter = config('load-guard.http.retry_after', 60);

            return response()->json([
                'error' => 'Service temporarily overloaded',
                'retry_after' => $retryAfter,
            ], 503)->header('Retry-After', $retryAfter);
        }

        return $next($request);
    }
}
