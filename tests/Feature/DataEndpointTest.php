<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\GridServiceProvider;
use AmjadIqbal\GridJS\Tests\Support\Team;
use AmjadIqbal\GridJS\Tests\Support\User;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $red = Team::create(['name' => 'Red']);
    $blue = Team::create(['name' => 'Blue']);
    foreach ([['Alice', $red, true], ['Bob', $blue, true], ['Charlie', $red, false], ['Dave', $blue, true], ['Eve', $red, true]] as $i => [$name, $team, $active]) {
        User::create(['name' => $name, 'email' => strtolower($name).'@example.com', 'team_id' => $team->id, 'active' => $active, 'joined_at' => '2026-0'.($i + 1).'-15 10:00:00']);
    }
});

function fetchGrid($test, $grid, array $params = []): array
{
    return $test->getJson(route('gridjs.data', ['grid' => $grid->token(), ...$params]))->assertOk()->json();
}

it('paginates on the server with the real total', function () {
    $grid = Grid::fromQuery(User::query()->orderBy('id'))->columns(['id', 'name'])->pagination(2);

    expect(fetchGrid($this, $grid))->toBe(['data' => [[1, 'Alice'], [2, 'Bob']], 'total' => 5]);
    expect(fetchGrid($this, $grid, ['page' => 3])['data'])->toBe([[5, 'Eve']]);
});

it('searches the searchable columns, including relation columns', function () {
    $grid = Grid::fromQuery(User::query())->columns(['name', 'email', 'team.name'])->searchable()->pagination(10);

    expect(fetchGrid($this, $grid, ['search' => 'ali'])['data'])->toBe([['Alice', 'alice@example.com', 'Red']]);
    expect(fetchGrid($this, $grid, ['search' => 'blue'])['total'])->toBe(2);
});

it('limits search to the listed columns', function () {
    $grid = Grid::fromQuery(User::query())->columns(['name', 'email'])->searchable(['name']);

    expect(fetchGrid($this, $grid, ['search' => 'example'])['total'])->toBe(0);
});

it('sorts on allowed fields and ignores anything else', function () {
    $grid = Grid::fromQuery(User::query()->orderBy('id'))->columns(['id', 'name', 'email'])->sortable(['name']);

    expect(array_column(fetchGrid($this, $grid, ['sort' => 'name', 'direction' => 'desc'])['data'], 1))->toBe(['Eve', 'Dave', 'Charlie', 'Bob', 'Alice']);
    expect(array_column(fetchGrid($this, $grid, ['sort' => 'email', 'direction' => 'desc'])['data'], 0))->toBe([1, 2, 3, 4, 5]);
    expect(array_column(fetchGrid($this, $grid, ['sort' => 'password', 'direction' => 'desc'])['data'], 0))->toBe([1, 2, 3, 4, 5]);
});

it('keeps the constraints and default order of the original query', function () {
    $grid = Grid::fromQuery(User::query()->where('active', true)->whereHas('team', fn ($q) => $q->where('name', 'Red'))->latest('joined_at'))
        ->columns(['name'])->pagination(10);

    expect(fetchGrid($this, $grid))->toBe(['data' => [['Eve'], ['Alice']], 'total' => 2]);
});

it('returns relation values, accessors and dates', function () {
    $grid = Grid::fromQuery(User::query()->with('team')->orderBy('id'))->columns(['team.name', 'shout', 'joined_at', 'active'])->pagination(1);

    expect(fetchGrid($this, $grid)['data'][0])->toBe(['Red', 'ALICE', '2026-01-15T10:00:00+00:00', true]);
});

it('returns the hidden key column for actions and null for the actions cell', function () {
    $grid = Grid::fromQuery(User::query()->orderBy('id'))->columns(['name'])->actions([['label' => 'Edit', 'url' => '/u/{id}']])->pagination(1);

    expect(fetchGrid($this, $grid)['data'][0])->toBe(['Alice', null, 1]);
});

it('caps a grid without pagination at max_rows', function () {
    config(['gridjs.max_rows' => 3]);

    expect(fetchGrid($this, Grid::fromQuery(User::query())->columns(['name'])))->toMatchArray(['total' => 3]);
});

it('refuses missing, tampered or foreign tokens', function () {
    $this->getJson(route('gridjs.data'))->assertForbidden();
    $this->getJson(route('gridjs.data', ['grid' => 'nope']))->assertForbidden();
    $this->getJson(route('gridjs.data', ['grid' => Crypt::encryptString(json_encode(['v' => 1, 'model' => stdClass::class]))]))->assertForbidden();
});

it('does not accept the old model and columns query parameters', function () {
    $this->getJson(route('gridjs.data', ['model' => User::class, 'columns' => 'email']))->assertForbidden();
});

it('applies the configured middleware and prefix', function () {
    $route = app('router')->getRoutes()->getByName('gridjs.data');

    expect($route->uri())->toBe('gridjs/data')->and($route->middleware())->toContain('web');
});

it('keeps grid data behind auth when the middleware asks for it', function () {
    config(['gridjs.middleware' => ['web', 'auth']]);
    (new GridServiceProvider(app()))->boot();
    app('router')->getRoutes()->refreshNameLookups();
    app('router')->getRoutes()->refreshActionLookups();

    $grid = Grid::fromQuery(User::query())->columns(['name']);

    $this->getJson(route('gridjs.data', ['grid' => $grid->token()]))->assertUnauthorized();
});
