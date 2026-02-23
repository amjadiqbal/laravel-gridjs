<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;

it('injects formatter into columns JS', function () {
    $html = Grid::fromQuery(User::query())
        ->columns([
            ['field' => 'id', 'name' => 'ID'],
            ['field' => 'email', 'name' => 'Email', 'formatter' => 'cell => cell.toUpperCase()'],
        ])
        ->render()
        ->toHtml();

    expect($html)->toContain("formatter:");
    expect($html)->toContain("cell => cell.toUpperCase()");
});
