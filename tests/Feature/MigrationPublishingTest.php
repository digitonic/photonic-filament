<?php

use Digitonic\Photonic\Filament\PhotonicServiceProvider;
use Illuminate\Support\Facades\Artisan;

it('publishes a migration filename', function () {
    // Booting the provider should register publish paths.
    $this->app->register(PhotonicServiceProvider::class);

    $paths = app('files')->glob(database_path('migrations/create_photonic_table.php'));

    // Ensure no timestamped migration exists before publish
    expect($paths)->toBeArray();

    Artisan::call('vendor:publish', [
        '--provider' => PhotonicServiceProvider::class,
        '--tag' => 'photonic-filament-migrations',
    ]);

    $published = app('files')->glob(database_path('migrations/create_photonic_table.php'));

    expect($published)->toHaveCount(1);
    expect($published[0])->toBeString();
});
