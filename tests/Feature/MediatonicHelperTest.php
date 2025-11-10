<?php

use Digitonic\Mediatonic\Filament\Models\Media;

it('returns null when filename empty', function () {
    expect(mediatonic_asset('', 'original'))->toBeNull();
});

it('builds original preset URL', function () {
    config()->set('mediatonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url = mediatonic_asset('photo.jpg', $assetUuid);
    expect($url)->toBe('https://cdn.example.com/site-123/'.$assetUuid.'/original/photo.jpg');
});

it('builds preset URL with webp extension conversion', function () {
    config()->set('mediatonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url = mediatonic_asset('photo.PNG', $assetUuid, 'featured');
    expect($url)->toBe('https://cdn.example.com/site-123/'.$assetUuid.'/presets/featured/photo.webp');
});

it('treats ORIGINAL case-insensitively', function () {
    config()->set('mediatonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url1 = mediatonic_asset('photo.jpg', $assetUuid, 'ORIGINAL');
    $url2 = mediatonic_asset('photo.jpg', $assetUuid, 'original');
    expect($url1)->toBe($url2);
});

it('returns configured table name via helper', function () {
    // default
    expect(get_mediatonic_table_name())->toBe((new Media)->getTable());

    // custom model
    $custom = new class extends Media
    {
        public function getTable(): string
        {
            return 'custom_media_table';
        }
    };

    config()->set('mediatonic-filament.media_model', $custom::class);

    expect(get_mediatonic_table_name())->toBe('custom_media_table');
});
