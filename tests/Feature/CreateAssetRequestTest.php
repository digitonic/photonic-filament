<?php

use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;
use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\Requests\CreateAsset;
use Saloon\Data\MultipartValue;

it('builds multipart body with site uuid and file field', function () {
    config()->set('mediatonic-filament.site_uuid', 'site-xyz');
    config()->set('mediatonic-filament.file_field', 'file');

    // Mock a file-like object with required methods
    $file = new class
    {
        public function getRealPath()
        {
            return __FILE__;
        }

        public function getClientOriginalName()
        {
            return 'example.jpg';
        }
    };

    // Handle both local and S3 storage scenarios
    $filePath = $file->getRealPath();
    if (! file_exists($filePath)) {
        // Livewire is using S3 or cloud storage, read from temporary URL
        $fileStream = fopen($file->temporaryUrl(), 'r');
    } else {
        // Local storage
        $fileStream = fopen($filePath, 'r');
    }

    // Correctly instantiate API connector
    $api = new API;
    $request = new CreateAsset(
        siteId: null,
        fileStream: $fileStream,
        fileName: $file->getClientOriginalName()
    );

    $body = $request->defaultBody();

    expect($body)->toBeArray()->and(count($body))->toBe(2);

    /** @var MultipartValue $sitePart */
    $sitePart = $body[0];
    /** @var MultipartValue $filePart */
    $filePart = $body[1];

    expect($sitePart->name)->toBe('site_uuid');
    expect($filePart->name)->toBe('file');
    expect($filePart->filename)->toBe('example.jpg');
});
