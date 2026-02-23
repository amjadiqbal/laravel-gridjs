<?php

namespace AmjadIqbal\GridJS\Console;

use Illuminate\Console\Command;

class PublishAssetsCommand extends Command
{
    protected $signature = 'gridjs:publish-assets {--path=public/vendor/gridjs}';
    protected $description = 'Download Grid.js UMD and theme CSS to a local public path';

    public function handle()
    {
        $path = $this->option('path');
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $scriptUrl = (string) config('gridjs.assets.script');
        $localScript = (string) config('gridjs.assets.local.script', 'gridjs.umd.js');
        $themes = (array) config('gridjs.assets.themes', []);
        $localThemes = (array) config('gridjs.assets.local.themes', []);

        if ($scriptUrl) {
            $data = @file_get_contents($scriptUrl);
            if ($data !== false) {
                file_put_contents($path . '/' . $localScript, $data);
                $this->info('Downloaded ' . $localScript);
            } else {
                $this->error('Failed to download script from ' . $scriptUrl);
            }
        }

        foreach (['mermaid', 'skeleton'] as $key) {
            $url = $themes[$key] ?? null;
            $file = $localThemes[$key] ?? ($key . '.min.css');
            if ($url) {
                $data = @file_get_contents($url);
                if ($data !== false) {
                    file_put_contents($path . '/' . $file, $data);
                    $this->info('Downloaded ' . $file);
                } else {
                    $this->error('Failed to download theme from ' . $url);
                }
            }
        }

        $this->info('Grid.js assets published to ' . $path);
        return self::SUCCESS;
    }
}
