<?php

namespace Digitonic\Photonic\Filament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PhotonicServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('photonic-filament')
            ->hasConfigFile('photonic-filament')
            ->discoversMigrations()
            ->runsMigrations()
            ->hasViews();
    }
}
