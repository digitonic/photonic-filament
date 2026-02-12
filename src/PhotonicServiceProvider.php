<?php

namespace Digitonic\Photonic\Filament;

use Digitonic\Photonic\Filament\Console\InstallCommand;
use Digitonic\Photonic\Filament\Livewire\PhotonicMediaManager;
use Digitonic\Photonic\Filament\Support\PhotonicManager;
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
        $this->app->singleton('photonic', static fn (): PhotonicManager => new PhotonicManager);

        $registerLivewireComponent = static function (): void {
            Livewire::component('photonic-media-manager', PhotonicMediaManager::class);
        };

        // Livewire 4 / Filament 5 can be registered after this provider in Testbench.
        // Register immediately when available, otherwise defer until Livewire resolves.
        if ($this->app->bound('livewire.finder')) {
            $registerLivewireComponent();
        } else {
            $this->callAfterResolving('livewire', $registerLivewireComponent);
        }

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
