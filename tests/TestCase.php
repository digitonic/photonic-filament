<?php

namespace Digitonic\Mediatonic\Filament\Tests;

use Digitonic\Mediatonic\Filament\MediatonicServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MediatonicServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // In-memory sqlite for fast tests
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Base config defaults for tests
        $app['config']->set('mediatonic.endpoint', 'https://api.example.test');
        $app['config']->set('mediatonic.api_key', 'test-token');
        $app['config']->set('mediatonic.site_uuid', 'site-123');
        $app['config']->set('mediatonic.cdn_endpoint', 'https://cdn.example.test');
    }
}
