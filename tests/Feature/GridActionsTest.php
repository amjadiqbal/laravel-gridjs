<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;

it('renders actions column with buttons', function () {
    $html = Grid::fromQuery(User::query())
        ->columns([['field' => 'id', 'name' => 'ID']])
        ->actions([
            ['label' => 'View', 'baseUrl' => '/users', 'class' => 'btn-view'],
            ['label' => 'Edit', 'baseUrl' => '/users/edit', 'class' => 'btn-edit'],
        ], idIndex: 0)
        ->render()
        ->toHtml();

    expect($html)->toContain('btn-view');
    expect($html)->toContain('btn-edit');
    expect($html)->toContain('gridjs.html');
});
