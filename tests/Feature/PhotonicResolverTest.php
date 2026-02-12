<?php

use Digitonic\Photonic\Filament\Data\PhotonicInfo;
use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Models\Media;
use Digitonic\Photonic\Filament\Support\PhotonicResolver;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $migration = include __DIR__.'/../../stubs/create_photonic_table.php.stub';
    $migration->up();
});

it('resolves url from media instance for original preset', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'resolver-uuid-original',
        'filename' => 'photo.jpg',
    ]);

    $url = (new PhotonicResolver)
        ->for($media)
        ->url();

    expect($url)->toBe('https://cdn.example.com/site-123/resolver-uuid-original/original/photo.jpg');
});

it('resolves url from media instance for non-original preset', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'resolver-uuid-featured',
        'filename' => 'photo.png',
    ]);

    $url = (new PhotonicResolver)
        ->for($media)
        ->preset('featured')
        ->url();

    expect($url)->toBe('https://cdn.example.com/site-123/resolver-uuid-featured/presets/featured/photo.webp');
});

it('resolves media by id with cache-aware lookup', function () {
    $media = Media::create([
        'asset_uuid' => 'resolver-cache-uuid',
        'filename' => 'cache.jpg',
    ]);

    $resolver = (new PhotonicResolver)
        ->for($media->id);

    $first = $resolver->media();
    DB::table('photonic')->where('id', $media->id)->delete();
    $second = $resolver->media();

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second?->filename)->toBe('cache.jpg');
});

it('returns null for missing id', function () {
    $resolved = (new PhotonicResolver)
        ->for(999999)
        ->media();

    expect($resolved)->toBeNull();
});

it('accepts preset enum and string', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'resolver-enum-uuid',
        'filename' => 'enum.jpg',
    ]);

    $urlWithEnum = (new PhotonicResolver)
        ->for($media)
        ->preset(PresetEnum::ORIGINAL)
        ->url();

    $urlWithString = (new PhotonicResolver)
        ->for($media)
        ->preset('original')
        ->url();

    expect($urlWithEnum)->toBe($urlWithString);
});

it('builds auto preset url from enum', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'resolver-auto-uuid',
        'filename' => 'auto.png',
    ]);

    $url = (new PhotonicResolver)
        ->for($media)
        ->preset(PresetEnum::AUTO)
        ->url();

    expect($url)->toBe('https://cdn.example.com/site-123/resolver-auto-uuid/presets/auto/auto.webp');
});

it('returns immutable info dto with serialization', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'resolver-info-uuid',
        'filename' => 'info.jpg',
        'alt' => 'Info alt',
        'title' => 'Info title',
        'description' => 'Info description',
        'caption' => 'Info caption',
        'config' => ['quality' => 80],
    ]);

    $info = (new PhotonicResolver)
        ->for($media)
        ->preset('original')
        ->info();

    expect($info)->toBeInstanceOf(PhotonicInfo::class)
        ->and($info?->toArray()['assetUuid'])->toBe('resolver-info-uuid')
        ->and($info?->jsonSerialize()['filename'])->toBe('info.jpg')
        ->and($info?->url)->toBe('https://cdn.example.com/site-123/resolver-info-uuid/original/info.jpg');

    expect(fn () => $info->filename = 'new.jpg');
});
