<?php

namespace Digitonic\MediaTonic\Filament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaTonicServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mediatonic-filament')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasViews();
    }
}
