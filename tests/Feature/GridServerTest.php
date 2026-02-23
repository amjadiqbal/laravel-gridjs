<?php

use AmjadIqbal\GridJS\Tests\Support\User;
use Illuminate\Support\Facades\Route;

it('returns paginated JSON with search and sort', function () {
    User::query()->insert([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
        ['name' => 'Charlie', 'email' => 'charlie@example.com'],
        ['name' => 'David', 'email' => 'david@example.com'],
    ]);

    $model = User::class;
    $columns = 'name,email';

    $resp = $this->getJson(route('gridjs.data', [
        'model' => $model,
        'columns' => $columns,
        'limit' => 2,
        'page' => 1,
        'search' => 'a',
        'sort' => 'name',
        'direction' => 'asc',
    ], false));

    $resp->assertOk();
    $json = $resp->json();

    expect($json)->toHaveKeys(['data', 'total']);
    expect($json['total'])->toBeGreaterThan(0);
    expect($json['data'][0])->toHaveCount(2);
});
