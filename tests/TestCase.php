<?php

namespace Digitonic\MediaTonic\Filament\Tests;

use Digitonic\MediaTonic\Filament\MediaTonicServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            MediaTonicServiceProvider::class,
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
        $app['config']->set('mediatonic-filament.endpoint', 'https://api.example.test');
        $app['config']->set('mediatonic-filament.api_key', 'test-token');
        $app['config']->set('mediatonic-filament.site_uuid', 'site-123');
        $app['config']->set('mediatonic-filament.cdn_endpoint', 'https://cdn.example.test');
    }
}
