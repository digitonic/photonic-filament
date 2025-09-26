<?php

namespace Digitonic\Filament\Lume;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LumeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-lume')
            ->hasConfigFile()
            ->hasMigration('create_lume_media_table')
            ->hasViews();
    }
}
