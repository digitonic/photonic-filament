<?php

namespace Digitonic\Photonic\Filament\Tests;

use Digitonic\Photonic\Filament\PhotonicServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PhotonicServiceProvider::class,
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
        $app['config']->set('photonic-filament.endpoint', 'https://api.example.test');
        $app['config']->set('photonic-filament.api_key', 'test-token');
        $app['config']->set('photonic-filament.site_uuid', 'site-123');
        $app['config']->set('photonic-filament.cdn_endpoint', 'https://cdn.example.test');
    }
}
