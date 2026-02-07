<?php

namespace IndieSystems\LoadGuard\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use IndieSystems\LoadGuard\Console\StatusCommand;
use IndieSystems\LoadGuard\Console\WatchCommand;
use IndieSystems\LoadGuard\LoadGuardManager;
use IndieSystems\LoadGuard\Middleware\RejectWhenOverloaded;
use IndieSystems\LoadGuard\Middleware\ThrottleWhenOverloaded;
use IndieSystems\LoadGuard\Readers\NativeReader;
use IndieSystems\LoadGuard\Readers\NullReader;
use IndieSystems\LoadGuard\Readers\ReaderInterface;

class LoadGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/load-guard.php', 'load-guard');

        $this->app->singleton(ReaderInterface::class, function () {
            $reader = config('load-guard.reader', 'auto');

            if ($reader === 'native') {
                return new NativeReader();
            }

            if ($reader === 'null') {
                return new NullReader();
            }

            // Auto-detect: use NativeReader on Linux when /proc exists
            if (is_dir('/proc') && is_readable('/proc/meminfo')) {
                return new NativeReader();
            }

            return new NullReader();
        });

        $this->app->singleton(LoadGuardManager::class, function ($app) {
            return new LoadGuardManager($app->make(ReaderInterface::class));
        });

        $this->app->alias(LoadGuardManager::class, 'load-guard');
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('load-guard.throttle', ThrottleWhenOverloaded::class);
        $router->aliasMiddleware('load-guard.reject', RejectWhenOverloaded::class);

        if (config('load-guard.health_check.enabled', true)) {
            $this->registerHealthRoute();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                StatusCommand::class,
                WatchCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/load-guard.php' => config_path('load-guard.php'),
            ], 'load-guard-config');
        }
    }

    protected function registerHealthRoute(): void
    {
        $path = config('load-guard.health_check.path', 'load-guard/health');
        $middleware = config('load-guard.health_check.middleware', []);

        $this->app['router']->group([
            'prefix' => $path,
            'middleware' => $middleware,
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/health.php');
        });
    }
}
