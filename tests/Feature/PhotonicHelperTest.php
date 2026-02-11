<?php

use Digitonic\Photonic\Filament\Models\Media;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $migration = include __DIR__.'/../../stubs/create_photonic_table.php.stub';
    $migration->up();
});

it('returns null when filename empty', function () {
    expect(photonic_asset('', 'original'))->toBeNull();
});

it('builds original preset URL', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url = photonic_asset('photo.jpg', $assetUuid);
    expect($url)->toBe('https://cdn.example.com/site-123/'.$assetUuid.'/original/photo.jpg');
});

it('builds preset URL with webp extension conversion', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url = photonic_asset('photo.PNG', $assetUuid, 'featured');
    expect($url)->toBe('https://cdn.example.com/site-123/'.$assetUuid.'/presets/featured/photo.webp');
});

it('treats ORIGINAL case-insensitively', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');
    $assetUuid = Str::uuid()->toString();

    $url1 = photonic_asset('photo.jpg', $assetUuid, 'ORIGINAL');
    $url2 = photonic_asset('photo.jpg', $assetUuid, 'original');
    expect($url1)->toBe($url2);
});

it('returns configured table name via helper', function () {
    // default
    expect(get_photonic_table_name())->toBe((new Media)->getTable());

    // custom model
    $custom = new class extends Media
    {
        public function getTable(): string
        {
            return 'custom_media_table';
        }
    };

    config()->set('photonic-filament.media_model', $custom::class);

    expect(get_photonic_table_name())->toBe('custom_media_table');
});

it('resolves media by id through fluent resolver path', function () {
    $media = Media::create([
        'asset_uuid' => 'helper-media-uuid',
        'filename' => 'helper-media.jpg',
    ]);

    $resolved = photonic_media_by_id($media->id);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->id)->toBe($media->id);
});

it('resolves asset url by id through fluent resolver path', function () {
    config()->set('photonic-filament.cdn_endpoint', 'https://cdn.example.com');
    config()->set('photonic-filament.site_uuid', 'site-123');

    $media = Media::create([
        'asset_uuid' => 'helper-asset-uuid',
        'filename' => 'helper-asset.jpg',
    ]);

    $url = photonic_asset_by_id($media->id, 'original');

    expect($url)->toBe('https://cdn.example.com/site-123/helper-asset-uuid/original/helper-asset.jpg');
});

it('uses cached media resolution for id-based fluent path', function () {
    $media = Media::create([
        'asset_uuid' => 'cache-media-uuid',
        'filename' => 'cache-media.jpg',
    ]);

    $first = photonic_media_by_id($media->id);
    DB::table('photonic')->where('id', $media->id)->delete();
    $second = photonic_media_by_id($media->id);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($second?->filename)->toBe('cache-media.jpg');
});
