<?php

namespace Digitonic\Mediatonic\Filament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediatonicServiceProvider extends PackageServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/mediatonic.php' => config_path('mediatonic.php'),
        ]);
    }
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mediatonic-filament')
            ->hasConfigFile()
            ->hasMigration('create_mediatonic_table')
            ->hasViews();
    }
}
