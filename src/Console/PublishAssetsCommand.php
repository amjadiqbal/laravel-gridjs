<?php

namespace AmjadIqbal\GridJS\Console;

use AmjadIqbal\GridJS\GridServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Copies the Grid.js build bundled with the package into your public folder. No download:
 * the files ship in the package, so this works offline and always matches the pinned version.
 */
class PublishAssetsCommand extends Command
{
    protected $signature = 'gridjs:publish-assets
        {--path=public/vendor/gridjs : Destination folder, relative to the project root}
        {--force : Overwrite existing files}';

    protected $description = 'Copy the bundled Grid.js script and theme into public/vendor/gridjs';

    public function handle(Filesystem $files): int
    {
        $path = (string) $this->option('path');
        $target = str_starts_with($path, '/') ? $path : base_path($path);
        $files->ensureDirectoryExists($target);

        foreach (['gridjs.umd.js', 'mermaid.min.css', 'LICENSE-gridjs'] as $file) {
            $destination = $target.'/'.$file;
            if ($files->exists($destination) && ! $this->option('force')) {
                $this->components->twoColumnDetail($file, 'exists, skipped (use --force)');

                continue;
            }
            $files->copy(__DIR__.'/../../resources/dist/'.$file, $destination);
            $this->components->twoColumnDetail($file, 'copied');
        }

        $this->components->info('Grid.js '.GridServiceProvider::GRIDJS_VERSION.' published to '.$path.'. Set GRIDJS_CDN=false to serve it.');

        return self::SUCCESS;
    }
}
