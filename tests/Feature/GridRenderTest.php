<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;

it('renders HTML/JS with correct Grid.js initialization', function () {
    $builder = User::query();

    $html = Grid::fromQuery($builder)
        ->columns(['id', 'name', 'email'])
        ->searchable()
        ->sortable()
        ->pagination(10)
        ->resizable()
        ->fixedHeader()
        ->theme('mermaid')
        ->render()
        ->toHtml();

    expect($html)->toContain('new gridjs.Grid');
    expect($html)->toContain('"columns":["id","name","email"]');
    expect($html)->toContain('server');
    expect($html)->toContain('gridjs/data');
});
