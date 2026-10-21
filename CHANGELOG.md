# Changelog

All notable changes to `amjadiqbal/laravel-gridjs` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-10-21

### Security

- Hidden columns no longer leave the server. A column marked `'hidden' => true` was sent to the browser in
  every JSON response, only hidden from display. Its values are now left out, and hidden columns can't be
  made searchable or sortable (search would let anyone probe their values). The only exception is the key
  column an `actions()` column needs to build its links.
- Grid tokens are now bound and expire. A token was valid for anyone, forever: copied from the page source
  it could be replayed by another user or after logout. Tokens are now bound to the signed-in user (or,
  for guests, the browser session) that rendered the grid and expire after `token_lifetime` minutes (720
  by default). A mismatched or expired token gets a 403. New config: `bind_tokens`, `guard`,
  `token_lifetime`.

### Changed

- Homepage and docs now point at https://amjad.com.pk/laravel/packages/laravel-gridjs.

## [1.0.0] - 2026-10-21

First tagged release, rebuilt against the real Grid.js 6.2.0 API. The untagged `dev-main` code it
replaces could not be installed with a plain `composer require`, did not work on Laravel 12 or 13, and
had these defects:

- Server-side pagination, search and sort were never wired to Grid.js (`pagination.server`,
  `search.server`, `sort.server`), so a grid only ever showed the first 10 rows, and search and sort ran
  in the browser over those 10 rows.
- **Security:** the data endpoint took the model class and column list from the query string, so anyone
  could read any column of any Eloquent model (for example `?model=App\Models\User&columns=password`).
- The query passed to `fromQuery()` was thrown away: `User::where('active', true)` showed every user.
- The `skeleton` theme URL returns 404 (Grid.js 6 ships only `mermaid`), CDN URLs were unpinned, and
  `gridjs:publish-assets` downloaded files at run time.
- Two grids on one page threw `SyntaxError: redeclaration of const baseConfig`; assets were tracked in a
  static flag, so under Octane they were printed on the first request only.
- Link and action formatters inserted database values into HTML unescaped (stored XSS).
- `gridjs:install` printed a contact message and offered to open a browser window.

### Added

- Laravel 12 and 13 support (PHP 8.3+).
- Encrypted grid tokens: the data endpoint runs only the query, columns and search/sort fields of a grid
  you rendered. Tampered or missing tokens get a 403.
- Real server-side processing: `LIMIT/OFFSET` pagination with the true total, `LIKE` search across the
  searchable columns (case-insensitive, `ILIKE` on PostgreSQL), and sorting restricted to allowed fields.
- The original query is kept: where clauses, scopes, joins, eager loads and its default order.
- Relation columns (`'team.name'`), searchable through `whereHas`; accessors and casts in cells.
- `searchable([...])` / `sortable([...])` field lists and per-column `searchable` / `sortable` / `hidden`
  / `width`.
- Built-in formats `date`, `datetime`, `number`, `currency`, `boolean` and `link`, all escaped.
- `actions()` with `{id}` URL placeholders, keyed on any field (added as a hidden column if not shown).
- `height()`, `width()`, `language()`, `className()`, `options()` and `id()`.
- `@gridjsAssets` directive and `Grid::assets()`; assets and the runtime print once per request, with
  the CSP nonce from `Vite::useCspNonce()`.
- Grid.js 6.2.0 pinned on jsDelivr with Subresource Integrity, or bundled in the package and copied by
  `gridjs:publish-assets` (no download).
- `gridjs.middleware` config (add `auth` to keep grid data behind login) and `gridjs.max_rows` cap for
  grids without pagination.
- Grids are `Htmlable`, so `{{ $grid }}` works in Blade.
- An entry in `php artisan about`.

### Changed

- `actions($buttons, $key = 'id')`: the second argument is now a field name. An integer still works as
  a column index, and `baseUrl` buttons still append `/{id}`.
- Column headings default to `Str::headline()` of the field (`created_at` becomes `Created At`).
- The data endpoint is `GET /gridjs/data?grid=<token>`; the old `model` and `columns` parameters are gone.

### Removed

- The `skeleton` theme (it does not exist in Grid.js 6).
- The contact prompt and browser launch in `gridjs:install`.
