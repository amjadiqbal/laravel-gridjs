<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;
use Illuminate\Support\Facades\Route;

it('uses configured route prefix', function () {
    config(['gridjs.prefix' => 'datagrid']);
    Route::gridjsRoutes();

    $html = Grid::fromQuery(User::query())
        ->columns(['id', 'name'])
        ->render()
        ->toHtml();

    expect($html)->toContain('datagrid/data');
});
