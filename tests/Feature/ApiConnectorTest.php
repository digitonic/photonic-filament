<?php

use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;

it('resolves base url from config', function () {
    config()->set('mediatonic-filament.endpoint', 'https://api.example.test/v1');
    $api = new API;
    expect($api->resolveBaseUrl())->toBe('https://api.example.test/v1');
});

it('uses token authenticator with configured api key', function () {
    config()->set('mediatonic-filament.api_key', 'test-token');
    $api = new API;
    $auth = (new ReflectionClass($api))->getMethod('defaultAuth');
    $auth->setAccessible(true);
    $authenticator = $auth->invoke($api);
    expect($authenticator)->toHaveProperty('token', 'test-token');
});
