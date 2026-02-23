<?php

it('runs install with options and writes config', function () {
    $this->artisan('gridjs:install --prefix=datagrid --cdn=false --publish-assets=false --open-link=false')
        ->assertExitCode(0);

    $path = config_path('gridjs.php');
    expect(file_exists($path))->toBeTrue();
    $content = file_get_contents($path);
    expect($content)->toContain("'datagrid'");
    expect($content)->toContain("'cdn' => false");
});
