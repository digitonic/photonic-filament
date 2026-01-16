<?php

use Illuminate\Support\Facades\File;

it('writes env values to .env and placeholders to .env.example in non-interactive mode', function () {
    $root = $this->app->basePath();

    File::put($root.'/.env', "APP_NAME=Test\n");
    File::put($root.'/.env.example', "APP_NAME=Test\n");

    $this->artisan('photonic-filament:install', [
        '--no-interaction' => true,
        '--endpoint' => 'https://example.test/api/v1',
        '--cdn-endpoint' => 'https://cdn.example.test/photonic',
        '--site-uuid' => '11111111-1111-1111-1111-111111111111',
        '--api-key' => 'super-secret',
        '--file-field' => 'file',
        '--response-key' => 'original_filename',
        '--force' => true,
    ])->assertExitCode(0);

    $env = File::get($root.'/.env');
    expect($env)->toContain('PHOTONIC_ENDPOINT=https://example.test/api/v1');
    expect($env)->toContain('PHOTONIC_CDN_ENDPOINT=https://cdn.example.test/photonic');
    expect($env)->toContain('PHOTONIC_SITE_UUID=11111111-1111-1111-1111-111111111111');
    expect($env)->toContain('PHOTONIC_API_KEY=super-secret');

    $example = File::get($root.'/.env.example');
    expect($example)->toContain('PHOTONIC_ENDPOINT=https://example.test/api/v1');
    expect($example)->toContain('PHOTONIC_CDN_ENDPOINT=https://cdn.example.test/photonic');
    expect($example)->toContain('PHOTONIC_SITE_UUID=11111111-1111-1111-1111-111111111111');
    expect($example)->toContain('PHOTONIC_API_KEY=');
});

it('is idempotent when not forcing', function () {
    $root = $this->app->basePath();

    File::put($root.'/.env', "PHOTONIC_ENDPOINT=https://a.test\n");

    $this->artisan('photonic-filament:install', [
        '--no-interaction' => true,
        '--endpoint' => 'https://b.test',
        '--cdn-endpoint' => 'https://cdn.test',
        '--site-uuid' => '11111111-1111-1111-1111-111111111111',
        '--api-key' => 'super-secret',
    ])->assertExitCode(0);

    $env = File::get($root.'/.env');
    expect($env)->toContain('PHOTONIC_ENDPOINT=https://a.test');
});
