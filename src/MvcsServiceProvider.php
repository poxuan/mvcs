<?php

namespace Callmecsx\Mvcs;

use Callmecsx\Mvcs\Console\MakeMvcsAllConsole;
use Callmecsx\Mvcs\Console\MakeMvcsConsole;
use Callmecsx\Mvcs\Console\ImportMvcsDbConsole;
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
            foreach(scandir($stubs) as $dir) {
                if ($dir[0] != '.' && \is_dir($stubs.'/'.$dir)) {
                    $this->publishes([$stubs.'/'.$dir  => resource_path('stubs/'.$dir)]);
                }
            }
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
        $this->app->singleton('command.mvcs_make', function () {
            return new MakeMvcsConsole();
        });

        $this->app->singleton('command.mvcs_excel', function () {
            return new ImportMvcsDbConsole();
        });

        $this->app->singleton('command.mvcs_make_all', function () {
            return new MakeMvcsAllConsole();
        });

        $this->commands(['command.mvcs_make','command.mvcs_excel','command.mvcs_make_all']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.mvcs_make','command.mvcs_excel','command.mvcs_make_all'];
    }
}
