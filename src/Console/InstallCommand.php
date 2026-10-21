<?php

namespace AmjadIqbal\GridJS\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'gridjs:install
        {--local : Serve Grid.js from public/vendor/gridjs instead of the CDN}
        {--force : Overwrite an existing config/gridjs.php}';

    protected $description = 'Publish the Grid.js config and, optionally, local copies of its assets';

    public function handle(): int
    {
        $this->call('vendor:publish', array_filter([
            '--tag' => 'gridjs-config',
            '--force' => (bool) $this->option('force'),
        ]));

        $local = $this->option('local')
            || ($this->input->isInteractive() && $this->components->choice(
                'Where should Grid.js load from?',
                ['cdn' => 'jsDelivr CDN (pinned, with integrity hashes)', 'local' => 'My own public/vendor/gridjs folder'],
                'cdn'
            ) === 'local');

        if ($local) {
            $this->call('gridjs:publish-assets', ['--force' => (bool) $this->option('force')]);
            $this->components->warn('Add GRIDJS_CDN=false to your .env to serve the local copies.');
        }

        $this->components->info('Grid.js is ready. Render a grid with Grid::fromQuery(Model::query())->columns([...]).');

        return self::SUCCESS;
    }
}
