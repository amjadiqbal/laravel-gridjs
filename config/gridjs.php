<?php

return [
    'prefix' => 'gridjs',
    'assets' => [
        'cdn' => true,
        'script' => 'https://unpkg.com/gridjs/dist/gridjs.umd.js',
        'themes' => [
            'mermaid' => 'https://unpkg.com/gridjs/dist/theme/mermaid.min.css',
            'skeleton' => 'https://unpkg.com/gridjs/dist/theme/skeleton.min.css',
        ],
        'local' => [
            'public_url' => '/vendor/gridjs',
            'script' => 'gridjs.umd.js',
            'themes' => [
                'mermaid' => 'mermaid.min.css',
                'skeleton' => 'skeleton.min.css',
            ],
        ],
    ],
];
