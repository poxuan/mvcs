<?php

namespace Callmecsx\Mvcs;

use Callmecsx\Mvcs\Console\MakeCvmsConsole;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class MvcsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $source = realpath($raw = __DIR__ . '/../config/mvcs.php') ?: $raw;
        $stubs  = realpath($raw = __DIR__ . '/../stubs') ?: $raw;
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('mvcs.php')]);
            $this->publishes([$stubs  => resource_path('stubs')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('mvcs');
        }

        $this->mergeConfigFrom($source, 'mvcs');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.mvcs', function () {
            return new MakeCvmsConsole();
        });

        $this->commands(['command.mvcs']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.mvcs'];
    }
}
