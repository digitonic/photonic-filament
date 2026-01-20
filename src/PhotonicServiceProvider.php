<?php

namespace Digitonic\Photonic\Filament;

use Digitonic\Photonic\Filament\Console\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PhotonicServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('photonic-filament')
            ->hasCommand(InstallCommand::class)
            ->hasMigration('create_photonic_table');
    }

    public function bootingPackage(): void
    {
        // Ensure stable publish tags matching the README.
        $this->publishes([
            $this->package->basePath('/../config/photonic-filament.php') => config_path('photonic-filament.php'),
        ], 'photonic-filament-config');

        $this->publishes([
            $this->package->basePath('/../resources/views') => resource_path('views/vendor/photonic-filament'),
        ], 'photonic-filament-views');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'photonic-filament');

        parent::bootingPackage();
    }
}
