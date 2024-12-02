<?php

namespace Santosdave\VerteilWrapper;

use Illuminate\Support\ServiceProvider;
use Santosdave\VerteilWrapper\Monitoring\HealthMonitor;
use Santosdave\VerteilWrapper\Notifications\VerteilNotifier;
use Santosdave\VerteilWrapper\Security\SecureTokenStorage;
use Santosdave\VerteilWrapper\Services\VerteilService;

class VerteilServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/verteil.php' => config_path('verteil.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\VerteilHealthCheck::class,
                Console\Commands\VerteilCacheFlush::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/verteil.php', 'verteil');

        $this->app->singleton('verteil', function ($app) {
            return new VerteilService(config('verteil'));
        });


        // Register SecureTokenStorage
        $this->app->singleton(SecureTokenStorage::class, function ($app) {
            return new SecureTokenStorage();
        });

        // Register HealthMonitor with TokenStorage dependency
        $this->app->singleton(HealthMonitor::class, function ($app) {
            return new HealthMonitor(
                $app->make(SecureTokenStorage::class)
            );
        });

        $this->app->singleton(VerteilNotifier::class, function ($app) {
            return new VerteilNotifier(config('verteil.notifications'));
        });
    }
}
