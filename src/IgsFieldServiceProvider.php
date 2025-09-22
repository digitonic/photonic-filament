<?php

namespace Digitonic\Filament\IgsField;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class IgsFieldServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('igs-field')
            ->hasConfigFile()
            ->hasMigration('create_igs_media_table');
    }
}
