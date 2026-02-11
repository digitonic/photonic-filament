<?php

use Digitonic\Photonic\Filament\Data\PhotonicInfo;
use Digitonic\Photonic\Filament\Facades\Photonic;
use Digitonic\Photonic\Filament\Models\Media;
use Digitonic\Photonic\Filament\Support\PhotonicManager;

beforeEach(function () {
    $migration = include __DIR__.'/../../stubs/create_photonic_table.php.stub';
    $migration->up();
});

it('binds photonic manager into the container', function () {
    expect(app('photonic'))->toBeInstanceOf(PhotonicManager::class);
});

it('resolves make through facade and returns expected url media and info', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'facade-uuid',
        'filename' => 'facade.jpg',
        'alt' => 'Facade alt',
    ]);

    $resolver = Photonic::make()
        ->for($media->id)
        ->preset('original');

    $url = $resolver->url();
    $resolvedMedia = $resolver->media();
    $info = $resolver->info();

    expect($url)->toBe('https://cdn.example.com/site-123/facade-uuid/original/facade.jpg')
        ->and($resolvedMedia)->not->toBeNull()
        ->and($resolvedMedia?->id)->toBe($media->id)
        ->and($info)->toBeInstanceOf(PhotonicInfo::class)
        ->and($info?->assetUuid)->toBe('facade-uuid');
});
