<p align="center">
  <img src="assets/laravel-grid.png" alt="Laravel Grid.js Banner" width="100%" />
</p>

<p align="center">
  <a href="https://packagist.org/packages/amjadiqbal/laravel-gridjs"><img alt="Packagist" src="https://img.shields.io/packagist/v/amjadiqbal/laravel-gridjs.svg?color=007ec6"></a>
  <a href="https://packagist.org/packages/amjadiqbal/laravel-gridjs"><img alt="Downloads" src="https://img.shields.io/packagist/dt/amjadiqbal/laravel-gridjs.svg?color=43a047"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs/actions"><img alt="CI" src="https://img.shields.io/github/actions/workflow/status/amjadiqbal/laravel-gridjs/tests.yml?label=tests&color=0d47a1"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs"><img alt="License" src="https://img.shields.io/badge/license-MIT-4caf50.svg"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs"><img alt="Stars" src="https://img.shields.io/github/stars/amjadiqbal/laravel-gridjs?color=ffb300&style=social"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs"><img alt="Forks" src="https://img.shields.io/github/forks/amjadiqbal/laravel-gridjs?color=9e9d24&style=social"></a>
  <a href="https://php.net"><img alt="PHP Version" src="https://img.shields.io/badge/PHP-%3E%3D8.1-777bb3.svg"></a>
  <a href="https://laravel.com"><img alt="Laravel" src="https://img.shields.io/badge/Laravel-10%20%7C%2011-ff2d20.svg"></a>
  <a href="https://codecov.io/gh/amjadiqbal/laravel-gridjs"><img alt="Coverage" src="https://img.shields.io/codecov/c/github/amjadiqbal/laravel-gridjs?color=1e88e5"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs/issues"><img alt="Open Issues" src="https://img.shields.io/github/issues/amjadiqbal/laravel-gridjs.svg?color=d81b60"></a>
  <a href="https://github.com/amjadiqbal/laravel-gridjs/pulls"><img alt="PRs" src="https://img.shields.io/github/issues-pr/amjadiqbal/laravel-gridjs.svg?color=8e24aa"></a>
</p>

# Laravel Grid.js

The modern, dependency-free Datagrid for Laravel. A powerful Grid.js wrapper with Eloquent support, server-side pagination, and an elegant fluent API.

## Table of Contents
- Why Grid.js?
- Installation
- Quick Start
- Visual Examples
- Server-side JSON Schema
- Fluent API Reference
- Configuration
- Development
- Changelog
- Contributing

## Why Grid.js?
- No jQuery
- Tiny footprint (Grid.js UMD)
- Native Laravel integration (Eloquent + routes)
- Fluent, chainable API
- Server-side ready (search, sort, paginate)

## Installation

```bash
composer require amjadiqbal/laravel-gridjs
```

### Installation Wizard (Interactive)
- Run the installer to set defaults and optionally publish assets:

```bash
php artisan gridjs:install
```

- Prompts:
  - Route prefix (default: gridjs)
  - Use CDN or local assets
  - Publish local assets and path
  - Open contact page in browser

- Non-interactive usage:

```bash
php artisan gridjs:install \
  --prefix=datagrid \
  --cdn=false \
  --publish-assets=true \
  --assets-path=public/vendor/gridjs \
  --open-link=false \
  --contact-url=https://github.com/amjadiqbal/laravel-gridjs/issues
```

### Publish Assets Locally

```bash
php artisan gridjs:publish-assets --path=public/vendor/gridjs
```

## Quick Start

```php
use AmjadIqbal\GridJS\Facades\Grid;
use App\Models\User;

echo Grid::fromQuery(User::query())
    ->columns(['id', 'name', 'email'])
    ->searchable()
    ->sortable()
    ->pagination(10)
    ->theme('mermaid')
    ->render();
```

## Visual Examples

### Minimal Users Table

```php
use AmjadIqbal\GridJS\Facades\Grid;
use App\Models\User;

echo Grid::fromQuery(User::query())
    ->columns(['id', 'name', 'email'])
    ->pagination(15)
    ->render();
```

### Skeleton Theme + Fixed Header + Resizable

```php
echo Grid::fromQuery(User::query())
    ->columns(['id', 'name', 'email'])
    ->searchable()
    ->sortable()
    ->pagination(20)
    ->resizable()
    ->fixedHeader()
    ->theme('skeleton')
    ->render();
```

### Custom ClassName Theme

```php
echo Grid::fromQuery(User::query())
    ->columns(['id', 'name', 'email'])
    ->theme([
        'table' => 'w-full border border-slate-200 rounded-md',
        'thead' => 'bg-slate-50',
        'th'    => 'px-3 py-2 text-xs text-slate-600',
        'td'    => 'px-3 py-2 text-sm text-slate-800',
    ])
    ->render();
```

### Column Formatters

```php
echo Grid::fromQuery(User::query())
    ->columns([
        ['field' => 'id', 'name' => 'ID'],
        ['field' => 'email', 'name' => 'Email', 'formatter' => 'cell => cell.toUpperCase()'],
    ])
    ->render();
```

### Built-in Format Options

```php
// Date
echo Grid::fromQuery(User::query())
    ->columns([
        ['field' => 'created_at', 'name' => 'Joined', 'format' => ['type' => 'date', 'locale' => 'en-US']],
    ])
    ->render();

// Currency
echo Grid::fromQuery(User::query())
    ->columns([
        ['field' => 'balance', 'name' => 'Balance', 'format' => ['type' => 'currency', 'currency' => 'USD']],
    ])
    ->render();

// Link
echo Grid::fromQuery(User::query())
    ->columns([
        ['field' => 'email', 'name' => 'Email', 'format' => ['type' => 'link']],
    ])
    ->render();
```

### Actions Column

```php
echo Grid::fromQuery(User::query())
    ->columns([['field' => 'id', 'name' => 'ID'], 'name', 'email'])
    ->actions([
        ['label' => 'View', 'baseUrl' => '/users', 'class' => 'btn btn-sm'],
        ['label' => 'Edit', 'baseUrl' => '/users/edit', 'class' => 'btn btn-sm btn-warning'],
    ], idIndex: 0)
    ->render();
```

### Asset Options
- Configurable CDN injection via `config/gridjs.php`:

```php
return [
  'assets' => [
    'cdn' => true,
    'script' => 'https://unpkg.com/gridjs/dist/gridjs.umd.js',
    'themes' => [
      'mermaid' => 'https://unpkg.com/gridjs/dist/theme/mermaid.min.css',
      'skeleton' => 'https://unpkg.com/gridjs/dist/theme/skeleton.min.css',
    ],
  ],
];
```

### Custom Route Prefix

```php
// config/gridjs.php
return ['prefix' => 'datagrid'];
```

## Server-side JSON Schema
- Endpoint: `GET /{prefix}/data` (default: `/gridjs/data`)
- Query params:
  - `model`: FQCN of the Eloquent model
  - `columns`: comma-separated list of fields
  - `limit`: page size (int)
  - `page`: page number (int)
  - `search`: search string (optional)
  - `sort`: column name (optional)
  - `direction`: `asc` or `desc` (optional)
- Response:

```json
{
  "data": [
    ["1", "Alice", "alice@example.com"],
    ["2", "Bob", "bob@example.com"]
  ],
  "total": 42
}
```

## Fluent API Reference

| Method | Params | Returns | Notes |
|-------|--------|---------|-------|
| columns | array<string> $columns | self | Defines visible fields and server serialization order |
| sortable | bool $enabled = true | self | Enables Grid.js sort and maps to server `sort/direction` |
| searchable | bool $enabled = true | self | Enables Grid.js search and maps to server `search` |
| pagination | int $limit | self | Enables pagination and sets `limit`; server uses `page` |
| resizable | bool $enabled = true | self | Enables Grid.js column resizing |
| fixedHeader | bool $enabled = true | self | Enables Grid.js fixed header rendering |
| theme | string|array $theme | self | `'mermaid'`, `'skeleton'`, or `className` map |
| fromQuery | Builder $query | GridBuilder | Binds an Eloquent model to server route |
| render | — | HtmlString | Returns HTML/JS for Grid.js initialization |

## Configuration
- File: `config/gridjs.php`
- Options:
  - `prefix`: Route segment for the data endpoint (default: `gridjs`)

## Development
- Testing:

```bash
vendor/bin/pest
```

- Installer:
  - The installer prints a general contact message:
    - “If you are facing any issue or need development help, contact me: https://github.com/amjadiqbal/laravel-gridjs/issues”
  - You can pass a custom contact URL with `--contact-url`
  - Opening the contact page is optional via `--open-link` or prompt

- Local Assets:
  - Banner stored in `assets/banner.svg` and included in README

## Changelog
- See GitHub releases for a full changelog.

## Contributing
- Fork and create a feature branch
- Add tests for your changes
- Open a PR with a clear description and rationale
