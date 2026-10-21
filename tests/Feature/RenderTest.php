<?php

use AmjadIqbal\GridJS\Facades\Grid;
use AmjadIqbal\GridJS\Tests\Support\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;

function freshRequest(): void
{
    app()->forgetScopedInstances();
    Grid::clearResolvedInstances();
}

function spec(string $html): array
{
    preg_match('/LaravelGridJS\.mount\("[^"]+", (\{.*\}), \{.*\}\)/sU', $html, $m);

    return json_decode($m[1], true);
}

it('renders a container, the pinned CDN assets with integrity and a mount call', function () {
    $html = (string) Grid::fromQuery(User::query())->columns(['id', 'name', 'email'])->render();

    expect($html)
        ->toContain('class="laravel-gridjs"')
        ->toContain('https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js')
        ->toContain('integrity="sha384-y62I+ZvjNRolkugL/AMpUZykqrL6oqYxBruObmlDAhDpmapM0s+xgvVK+wGVpza0"')
        ->toContain('gridjs@6.2.0/dist/theme/mermaid.min.css')
        ->toContain('window.LaravelGridJS')
        ->toContain('LaravelGridJS.mount(');

    expect(spec($html)['columns'])->sequence(
        fn ($c) => $c->toMatchArray(['field' => 'id', 'name' => 'Id']),
        fn ($c) => $c->toMatchArray(['field' => 'name', 'name' => 'Name']),
        fn ($c) => $c->toMatchArray(['field' => 'email', 'name' => 'Email']),
    );
});

it('prints the assets and runtime once per request, however many grids there are', function () {
    $first = (string) Grid::fromQuery(User::query())->columns(['name'])->render();
    $second = (string) Grid::fromQuery(User::query())->columns(['email'])->render();

    expect($first)->toContain('gridjs.umd.js')->toContain('window.LaravelGridJS');
    expect($second)->not->toContain('gridjs.umd.js')->not->toContain('window.LaravelGridJS')->toContain('LaravelGridJS.mount(');
});

it('works as an Htmlable inside Blade echo tags', function () {
    $grid = Grid::fromQuery(User::query())->columns(['name']);

    expect(Blade::render('{{ $grid }}', ['grid' => $grid]))->toContain('LaravelGridJS.mount(')->not->toContain('&lt;div');
});

it('maps the fluent options to Grid.js config', function () {
    $html = (string) Grid::fromQuery(User::query())
        ->columns(['name', 'email', 'team.name'])
        ->searchable()
        ->sortable()
        ->pagination(25)
        ->resizable()
        ->fixedHeader(height: '300px')
        ->language(['search' => ['placeholder' => 'Find']])
        ->render();
    $spec = spec($html);

    expect($spec['search'])->toBe(['debounce' => 300])
        ->and($spec['sort'])->toBeTrue()
        ->and($spec['pagination'])->toBe(['limit' => 25])
        ->and($spec['options'])->toMatchArray(['resizable' => true, 'fixedHeader' => true, 'height' => '300px', 'language' => ['search' => ['placeholder' => 'Find']]])
        ->and($spec['columns'][0]['sortable'])->toBeTrue()
        ->and($spec['columns'][2])->not->toHaveKey('sortable')
        ->and($spec['columns'][2]['name'])->toBe('Team Name');
});

it('uses the configured default page size', function () {
    config(['gridjs.per_page' => 15]);

    expect(spec((string) Grid::fromQuery(User::query())->columns(['name'])->pagination()->render())['pagination'])->toBe(['limit' => 15]);
});

it('accepts a className map as the theme and skips the theme stylesheet', function () {
    $html = (string) Grid::fromQuery(User::query())->columns(['name'])->theme(['table' => 'w-full'])->render();

    expect($html)->not->toContain('mermaid.min.css');
    expect(spec($html)['options']['className'])->toBe(['table' => 'w-full']);
});

it('passes custom JS formatters through and built-in formats as data', function () {
    $html = (string) Grid::fromQuery(User::query())->columns([
        ['field' => 'email', 'formatter' => 'cell => cell.toUpperCase()'],
        ['field' => 'joined_at', 'name' => 'Joined', 'format' => ['type' => 'date', 'locale' => 'en-GB']],
    ])->render();

    expect($html)->toContain('{0: (cell => cell.toUpperCase())}');
    expect(spec($html)['columns'][1]['format'])->toBe(['type' => 'date', 'locale' => 'en-GB']);
});

it('adds an actions column and a hidden key column when the key is not shown', function () {
    $spec = spec((string) Grid::fromQuery(User::query())
        ->columns(['name'])
        ->actions([['label' => 'Edit', 'url' => '/users/{id}/edit'], ['label' => 'View', 'baseUrl' => '/users']])
        ->render());

    expect($spec['columns'])->toHaveCount(3)
        ->and($spec['columns'][1])->toMatchArray(['name' => 'Actions', 'keyIndex' => 2])
        ->and($spec['columns'][1]['actions'][1]['url'])->toBe('/users/{id}')
        ->and($spec['columns'][2])->toMatchArray(['field' => 'id', 'hidden' => true]);
});

it('keeps the actions column after columns() is called again', function () {
    $spec = spec((string) Grid::fromQuery(User::query())
        ->actions([['label' => 'Edit', 'url' => '/u/{id}']])
        ->columns(['id', 'name'])
        ->render());

    expect(array_column($spec['columns'], 'name'))->toBe(['Id', 'Name', 'Actions'])
        ->and($spec['columns'][2]['keyIndex'])->toBe(0);
});

it('escapes </script> inside column labels', function () {
    $html = (string) Grid::fromQuery(User::query())->columns([['field' => 'name', 'name' => '</script><b>x']])->render();

    expect($html)->not->toContain('</script><b>');
});

it('rejects invalid fields and server-side sorting on relation columns', function () {
    expect(fn () => Grid::fromQuery(User::query())->columns(['name; drop table users']))->toThrow(InvalidArgumentException::class);
    expect(fn () => Grid::fromQuery(User::query())->columns([['field' => 'team.name', 'sortable' => true]])->render())->toThrow(InvalidArgumentException::class);
    expect(fn () => Grid::fromQuery(User::query())->render())->toThrow(InvalidArgumentException::class);
});

it('serves local assets and leaves library tags out when injection is off', function () {
    config(['gridjs.assets.cdn' => false]);
    $local = (string) Grid::fromQuery(User::query())->columns(['name'])->render();
    expect($local)->toContain('/vendor/gridjs/gridjs.umd.js')->toContain('/vendor/gridjs/mermaid.min.css')->not->toContain('jsdelivr');

    freshRequest();
    config(['gridjs.assets.inject' => false]);
    $manual = (string) Grid::fromQuery(User::query())->columns(['name'])->render();
    expect($manual)->not->toContain('gridjs.umd.js')->toContain('window.LaravelGridJS');
});

it('renders the @gridjsAssets directive', function () {
    expect(Blade::render('@gridjsAssets'))->toContain('gridjs.umd.js')->toContain('mermaid.min.css');
    freshRequest();
    expect(Blade::render('@gridjsAssets(false)'))->toContain('gridjs.umd.js')->not->toContain('mermaid.min.css');
});

it('adds the Vite CSP nonce to inline scripts', function () {
    Vite::useCspNonce('abc123');

    expect((string) Grid::fromQuery(User::query())->columns(['name'])->render())->toContain('<script nonce="abc123">');
});
