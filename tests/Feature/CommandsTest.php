<?php

use Illuminate\Support\Facades\File;

it('copies the bundled Grid.js build without downloading anything', function () {
    $dir = sys_get_temp_dir().'/gridjs-publish-'.uniqid();

    $this->artisan('gridjs:publish-assets', ['--path' => $dir])->assertSuccessful();

    expect(File::exists("$dir/gridjs.umd.js"))->toBeTrue()
        ->and(File::exists("$dir/mermaid.min.css"))->toBeTrue()
        ->and(hash_file('sha384', "$dir/gridjs.umd.js", true))->toBe(base64_decode('y62I+ZvjNRolkugL/AMpUZykqrL6oqYxBruObmlDAhDpmapM0s+xgvVK+wGVpza0'));

    File::deleteDirectory($dir);
});

it('installs non-interactively with the CDN by default', function () {
    $this->artisan('gridjs:install', ['--no-interaction' => true, '--force' => true])->assertSuccessful();

    expect(File::exists(config_path('gridjs.php')))->toBeTrue();
    File::delete(config_path('gridjs.php'));
});

it('adds a passive entry to artisan about', function () {
    $this->artisan('about')->expectsOutputToContain('Grid.js datagrid')->assertSuccessful();
});
