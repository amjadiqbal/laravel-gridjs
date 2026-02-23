<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;
use Illuminate\Support\Facades\Route;

it('renders HTML/JS with correct Grid.js initialization', function () {
    config(['gridjs.prefix' => 'gridjs']);
    Route::gridjsRoutes();
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
