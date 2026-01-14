<?php

use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\CreateAsset;
use Saloon\Data\MultipartValue;

it('builds multipart body with site uuid and key field', function () {
    config()->set('photonic-filament.site_uuid', 'site-xyz');

    $api = new API;
    $request = new CreateAsset(
        siteId: 'site-xyz',
        key: 'uploads/example-key-12345.jpg',
        fileName: 'example.jpg'
    );

    $body = $request->defaultBody();

    expect($body)->toBeArray()->and(count($body))->toBe(3);

    /** @var MultipartValue $sitePart */
    $sitePart = $body[0];
    /** @var MultipartValue $filenamePart */
    $filenamePart = $body[1];
    /** @var MultipartValue $keyPart */
    $keyPart = $body[2];

    expect($sitePart->name)->toBe('site_uuid');
    expect($sitePart->value)->toBe('site-xyz');
    expect($filenamePart->name)->toBe('filename');
    expect($filenamePart->value)->toBe('example.jpg');
    expect($keyPart->name)->toBe('key');
    expect($keyPart->value)->toBe('uploads/example-key-12345.jpg');
});
