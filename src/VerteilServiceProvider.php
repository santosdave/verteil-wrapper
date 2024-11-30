<?php

namespace Santosdave\VerteilWrapper;

use Illuminate\Support\ServiceProvider;
use Santosdave\VerteilWrapper\Services\VerteilService;

class VerteilServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/verteil.php' => config_path('verteil.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/verteil.php', 'verteil');

        $this->app->singleton('verteil', function ($app) {
            return new VerteilService(config('verteil'));
        });
    }
}