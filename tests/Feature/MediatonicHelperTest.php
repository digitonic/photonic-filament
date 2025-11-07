<?php

use Digitonic\Mediatonic\Filament\Models\Media;

it('returns null when filename empty', function () {
    expect(mediatonic_asset('', 'original'))->toBeNull();
});

it('builds original preset URL', function () {
    config()->set('mediatonic.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic.site_uuid', 'site-123');

    $url = mediatonic_asset('photo.jpg', 'original');
    expect($url)->toBe('https://cdn.example.com/site-123/original/photo.jpg');
});

it('builds preset URL with webp extension conversion', function () {
    config()->set('mediatonic.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic.site_uuid', 'site-123');

    $url = mediatonic_asset('photo.PNG', 'featured');
    expect($url)->toBe('https://cdn.example.com/site-123/presets/featured/photo.webp');
});

it('treats ORIGINAL case-insensitively', function () {
    config()->set('mediatonic.cdn_endpoint', 'https://cdn.example.com');
    config()->set('mediatonic.site_uuid', 'site-123');

    $url1 = mediatonic_asset('photo.jpg', 'ORIGINAL');
    $url2 = mediatonic_asset('photo.jpg', 'original');
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

    config()->set('mediatonic.media_model', $custom::class);

    expect(get_mediatonic_table_name())->toBe('custom_media_table');
});
