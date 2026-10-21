<?php

namespace AmjadIqbal\GridJS;

use AmjadIqbal\GridJS\Console\InstallCommand;
use AmjadIqbal\GridJS\Console\PublishAssetsCommand;
use AmjadIqbal\GridJS\Http\Controllers\GridDataController;
use Composer\InstalledVersions;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class GridServiceProvider extends ServiceProvider
{
    public const SUPPORT_URL = 'https://amjad.com.pk/contact?utm_source=artisan&utm_medium=about&utm_campaign=laravel-gridjs';

    public const GRIDJS_VERSION = '6.2.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gridjs.php', 'gridjs');
        $this->app->scoped('gridjs', fn () => new GridManager);
        $this->app->alias('gridjs', GridManager::class);
    }

    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::prefix((string) config('gridjs.prefix', 'gridjs'))
                ->middleware((array) config('gridjs.middleware', ['web']))
                ->group(fn () => Route::get('data', GridDataController::class)->name('gridjs.data'));
        }

        Blade::directive('gridjsAssets', fn (string $theme) => '<?php echo app(\'gridjs\')->assets('.($theme !== '' ? $theme : '').'); ?>');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, PublishAssetsCommand::class]);
            $this->publishes([__DIR__.'/../config/gridjs.php' => config_path('gridjs.php')], 'gridjs-config');
            $this->publishes([
                __DIR__.'/../resources/dist/gridjs.umd.js' => public_path('vendor/gridjs/gridjs.umd.js'),
                __DIR__.'/../resources/dist/mermaid.min.css' => public_path('vendor/gridjs/mermaid.min.css'),
                __DIR__.'/../resources/dist/LICENSE-gridjs' => public_path('vendor/gridjs/LICENSE-gridjs'),
            ], 'gridjs-assets');
        }

        if (class_exists(AboutCommand::class)) {
            AboutCommand::add('Grid.js datagrid', fn () => [
                'Version' => InstalledVersions::isInstalled('amjadiqbal/laravel-gridjs')
                    ? (string) InstalledVersions::getPrettyVersion('amjadiqbal/laravel-gridjs')
                    : 'unknown',
                'Grid.js' => self::GRIDJS_VERSION.(config('gridjs.assets.cdn', true) ? ' (CDN)' : ' (local)'),
                'Support' => self::SUPPORT_URL,
            ]);
        }
    }
}
