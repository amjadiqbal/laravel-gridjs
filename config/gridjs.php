<?php

return [

    /*
    | Every grid fetches its rows from GET /{prefix}/data. The request carries an
    | encrypted token describing that one grid (model, query, columns), so the
    | endpoint can only ever return what a grid you rendered was allowed to show.
    | Add 'auth' (or any guard) to the middleware to keep grid data behind login.
    */
    'prefix' => env('GRIDJS_PREFIX', 'gridjs'),

    'middleware' => ['web'],

    /*
    | Each grid token is bound to whoever the page was rendered for: the signed-in
    | user (on the guard below, null = default), or for guests the current session.
    | A token copied to another browser or account gets a 403. Binding needs the
    | session, i.e. the 'web' middleware above; set 'bind_tokens' to false for a
    | stateless setup. Tokens also expire: an open page older than this needs a reload.
    */
    'bind_tokens' => true,

    // With binding on, a grid rendered with neither a signed-in user nor a session (no 'web'
    // middleware) throws instead of issuing a token anyone could replay. Set this to true to issue
    // unbound tokens there instead (they still expire).
    'allow_unbound_tokens' => false,

    'guard' => null,

    'token_lifetime' => 720, // minutes

    /*
    | Default page size for ->pagination() without a limit, and the cap on rows
    | a grid without pagination returns in one response.
    */
    'per_page' => 10,

    'max_rows' => 1000,

    /*
    | Default theme: 'mermaid' (the stylesheet Grid.js ships) or null for
    | unstyled markup you style yourself with ->className([...]).
    */
    'theme' => 'mermaid',

    'assets' => [

        // Print the script and stylesheet tags before the first grid on a page. Turn this
        // off when you load Grid.js yourself (Vite, or @gridjsAssets in your layout).
        'inject' => true,

        // true: jsDelivr, pinned to Grid.js 6.2.0 with Subresource Integrity.
        // false: the copies published by `php artisan gridjs:publish-assets`.
        'cdn' => env('GRIDJS_CDN', true),

        'script' => 'https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/gridjs.umd.js',
        'script_integrity' => 'sha384-y62I+ZvjNRolkugL/AMpUZykqrL6oqYxBruObmlDAhDpmapM0s+xgvVK+wGVpza0',

        'themes' => [
            'mermaid' => [
                'url' => 'https://cdn.jsdelivr.net/npm/gridjs@6.2.0/dist/theme/mermaid.min.css',
                'integrity' => 'sha384-i8iPOOXHyYKlqvjJjbORq7m/VrfUhgupTg3IZvtXz8M7c0CiTPUUhM5gdjiQiGbv',
            ],
        ],

        // Public URL of the files gridjs:publish-assets copies into public/vendor/gridjs.
        'local_url' => '/vendor/gridjs',
    ],

];
