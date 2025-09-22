<?php

namespace Digitonic\Filament\IgsField;

use Package;

class IgsFieldServiceProvider extends PackageServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/igs-field.php', 'igs-field');
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/igs-field.php' => config_path('igs-field.php'),
        ], 'igs-field-config');
    }
}
