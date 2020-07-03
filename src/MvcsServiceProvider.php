<?php

namespace Callmecsx\Mvcs;

use Callmecsx\Mvcs\Console\AppendMvcsConsole;
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
        $source = realpath($raw = __DIR__ . '/../config') ?: $raw;
        $stubs  = realpath($raw = __DIR__ . '/../stubs') ?: $raw;
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path()]);
            $this->publishes([$stubs  => resource_path('stubs')]);
            foreach(scandir($stubs) as $dir) {
                if ($dir[0] != '.' && \is_dir($stubs.'/'.$dir)) {
                    $this->publishes([$stubs.'/'.$dir  => resource_path('stubs/'.$dir)]);
                }
            }
            // 隐藏功能，把我的一些基础代码挪过去
            if (config('mvcs.base')) {
                $base  = realpath($raw = __DIR__ . '/../examples/Base') ?: $raw;
                $this->publishes([$base  => app_path('Base')]);
                foreach(scandir($base) as $dir) {
                    if ($dir[0] != '.' && \is_dir($base.'/'.$dir)) {
                        $this->publishes([$base.'/'.$dir  => app_path('Base/'.$dir)]);
                    }
                }
            }
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('mvcs');
        }

        // $this->mergeConfigFrom($source, 'mvcs');
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

        $this->app->singleton('command.mvcs_append', function () {
            return new AppendMvcsConsole();
        });

        $this->app->singleton('command.mvcs_excel', function () {
            return new ImportMvcsDbConsole();
        });

        $this->app->singleton('command.mvcs_make_all', function () {
            return new MakeMvcsAllConsole();
        });

        $this->commands(['command.mvcs_make','command.mvcs_append','command.mvcs_excel','command.mvcs_make_all']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.mvcs_make','command.mvcs_append','command.mvcs_excel','command.mvcs_make_all'];
    }
}
