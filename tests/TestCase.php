<?php

declare(strict_types=1);

namespace Gowelle\GoogleModerator\Tests;

use Gowelle\GoogleModerator\GoogleModeratorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            GoogleModeratorServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Moderation' => \Gowelle\GoogleModerator\Facades\Moderation::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
