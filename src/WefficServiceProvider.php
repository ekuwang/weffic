<?php

namespace Ekuwang\Weffic;

use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class WefficServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $source = realpath(__DIR__ . '/../config/weffic.php');

        if ($this->app instanceof LaravelApplication) {
            if ($this->app->runningInConsole()) {
                $this->publishes([
                    $source => config_path('weffic.php'),
                ]);
            }
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('weffic');
        }

        $this->mergeConfigFrom(
            realpath(__DIR__ . '/../config/weffic.php'), 'weffic'
        );

    }

    public function register()
    {
        $this->app->singleton('weffic', function () {
            return new Weffic(config('weffic'));
        });
    }
}
