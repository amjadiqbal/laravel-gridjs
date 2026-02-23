<?php

use AmjadIqbal\GridJS\GridConfig;

it('transforms arrays into Grid.js JSON schema', function () {
    $config = new GridConfig(
        columns: ['id', 'name', 'email'],
        sortable: true,
        searchable: true,
        paginationLimit: 15,
        resizable: true,
        fixedHeader: true,
        className: ['th' => 'text-xs', 'td' => 'text-sm']
    );

    $expected = [
        'columns' => ['id', 'name', 'email'],
        'sort' => true,
        'search' => true,
        'resizable' => true,
        'fixedHeader' => true,
        'pagination' => ['enabled' => true, 'limit' => 15],
        'className' => ['th' => 'text-xs', 'td' => 'text-sm'],
    ];

    expect(json_decode($config->toJson(), true))->toBe($expected);
});
