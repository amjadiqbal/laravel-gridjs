<?php

namespace AmjadIqbal\GridJS;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use AmjadIqbal\GridJS\Http\Controllers\GridDataController;
use AmjadIqbal\GridJS\Console\PublishAssetsCommand;
use AmjadIqbal\GridJS\Console\InstallCommand;

class GridServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gridjs.php', 'gridjs');
        $this->app->singleton('gridjs', function () {
            return new GridManager();
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PublishAssetsCommand::class,
                InstallCommand::class,
            ]);
            $this->publishes([
                __DIR__ . '/../config/gridjs.php' => config_path('gridjs.php'),
            ], 'gridjs-config');
        }
        Route::macro('gridjsRoutes', function () {
            $prefix = config('gridjs.prefix', 'gridjs');
            Route::prefix($prefix)->group(function () {
                Route::get('data', [GridDataController::class, 'index'])->name('gridjs.data');
            });
        });

        if (!app()->routesAreCached()) {
            Route::gridjsRoutes();
        }
    }
}
