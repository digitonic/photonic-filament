<?php

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API;

it('resolves base url from config', function () {
    config()->set('mediatonic.endpoint', 'https://api.example.test/v1');
    $api = new API;
    expect($api->resolveBaseUrl())->toBe('https://api.example.test/v1');
});

it('uses token authenticator with configured api key', function () {
    config()->set('mediatonic.api_key', 'test-token');
    $api = new API;
    $auth = (new ReflectionClass($api))->getMethod('defaultAuth');
    $auth->setAccessible(true);
    $authenticator = $auth->invoke($api);
    expect($authenticator)->toHaveProperty('token', 'test-token');
});
