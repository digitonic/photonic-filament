<?php

namespace Digitonic\Photonic\Filament;

use Digitonic\Photonic\Filament\Console\InstallCommand;
use Digitonic\Photonic\Filament\Livewire\PhotonicMediaManager;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PhotonicServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('photonic-filament')
            ->hasCommand(InstallCommand::class);
    }

    public function bootingPackage(): void
    {
        // Register Livewire component
        Livewire::component('photonic-media-manager', PhotonicMediaManager::class);

        // Publish config
        $this->publishes([
            $this->package->basePath('/../config/photonic-filament.php') => config_path('photonic-filament.php'),
        ], 'photonic-filament-config');

        // Publish views
        $this->publishes([
            $this->package->basePath('/../resources/views') => resource_path('views/vendor/photonic-filament'),
        ], 'photonic-filament-views');

        // Publish migrations without timestamp (for consistency with existing installations)
        $this->publishes([
            $this->package->basePath('/../stubs/create_photonic_table.php.stub') => database_path('migrations/create_photonic_table.php'),
        ], 'photonic-filament-migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'photonic-filament');

        parent::bootingPackage();
    }
}
