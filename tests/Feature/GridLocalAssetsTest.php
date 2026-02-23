<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;
use AmjadIqbal\GridJS\GridBuilder;

it('renders local asset paths when CDN disabled', function () {
    config([
        'gridjs.assets.cdn' => false,
        'gridjs.assets.local.public_url' => '/assets/gridjs',
    ]);
    GridBuilder::resetAssetsInjection();

    $html = Grid::fromQuery(User::query())
        ->columns(['id', 'name'])
        ->theme('mermaid')
        ->render()
        ->toHtml();

    expect($html)->toContain('/assets/gridjs/gridjs.umd.js');
    expect($html)->toContain('/assets/gridjs/mermaid.min.css');
    expect($html)->not->toContain('unpkg.com');
});
