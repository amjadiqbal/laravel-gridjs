<?php

namespace AmjadIqbal\GridJS\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AmjadIqbal\GridJS\GridServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GridServiceProvider::class,
            DatabaseServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $migration = require __DIR__ . '/database/migrations/0000_00_00_000000_create_users_table.php';
        $migration->up();
    }
}
