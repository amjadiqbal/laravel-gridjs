<?php

namespace AmjadIqbal\GridJS\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'gridjs:install
        {--prefix= : Route prefix}
        {--cdn= : Use CDN (true/false)}
        {--publish-assets= : Publish assets locally (true/false)}
        {--assets-path= : Local assets path}
        {--open-link= : Open contact page (true/false)}
        {--contact-url= : Contact URL}';

    protected $description = 'Interactive installation for Laravel Grid.js';

    public function handle()
    {
        $this->line('Welcome! I am Amjad Iqbal. Thanks for installing Laravel Grid.js.');
        $openOpt = $this->option('open-link');
        $link = $this->option('contact-url') ?: 'https://github.com/amjadiqbal/laravel-gridjs/issues';
        $this->line('If you are facing any issue or need development help, contact me: ' . $link);
        $open = $openOpt !== null ? (filter_var($openOpt, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false) : $this->confirm('Open the contact page now?', false);
        if ($open) {
            $this->openUrl($link);
            $this->info('Opening: ' . $link);
        }

        $prefix = $this->option('prefix') ?? $this->ask('Route prefix', 'gridjs');
        $cdnOpt = $this->option('cdn');
        if ($cdnOpt === null) {
            $cdn = $this->choice('Use CDN for Grid.js assets?', ['yes', 'no'], 0) === 'yes';
        } else {
            $cdn = filter_var($cdnOpt, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        $publishOpt = $this->option('publish-assets');
        if ($publishOpt === null) {
            $publishAssets = $this->choice('Publish local assets?', ['yes', 'no'], $cdn ? 1 : 0) === 'yes';
        } else {
            $publishAssets = filter_var($publishOpt, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
        }

        $assetsPath = $this->option('assets-path') ?? 'public/vendor/gridjs';
        $publicUrl = '/vendor/gridjs';
        if (str_starts_with($assetsPath, 'public/')) {
            $publicUrl = '/' . trim(substr($assetsPath, strlen('public/')), '/');
        }

        $this->publishConfig();
        $this->writeConfig($prefix, $cdn, $publicUrl);

        if ($publishAssets) {
            Artisan::call('gridjs:publish-assets', ['--path' => $assetsPath]);
            $this->info('Assets published to ' . $assetsPath);
        }

        $this->info('Installation complete.');
        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        try {
            Artisan::call('vendor:publish', ['--tag' => 'gridjs-config', '--force' => true]);
        } catch (\Throwable $e) {
        }
    }

    protected function writeConfig(string $prefix, bool $cdn, string $publicUrl): void
    {
        $path = config_path('gridjs.php');
        $content = "<?php\n\nreturn " . var_export([
            'prefix' => $prefix,
            'assets' => [
                'cdn' => $cdn,
                'script' => 'https://unpkg.com/gridjs/dist/gridjs.umd.js',
                'themes' => [
                    'mermaid' => 'https://unpkg.com/gridjs/dist/theme/mermaid.min.css',
                    'skeleton' => 'https://unpkg.com/gridjs/dist/theme/skeleton.min.css',
                ],
                'local' => [
                    'public_url' => $publicUrl,
                    'script' => 'gridjs.umd.js',
                    'themes' => [
                        'mermaid' => 'mermaid.min.css',
                        'skeleton' => 'skeleton.min.css',
                    ],
                ],
            ],
        ], true) . ";\n";
        @file_put_contents($path, $content);
    }

    protected function openUrl(string $url): void
    {
        $family = PHP_OS_FAMILY ?? '';
        if ($family === 'Windows') {
            @pclose(@popen('start "" "' . $url . '"', 'r'));
            return;
        }
        if ($family === 'Darwin') {
            @pclose(@popen('open "' . $url . '"', 'r'));
            return;
        }
        @pclose(@popen('xdg-open "' . $url . '"', 'r'));
    }
}
