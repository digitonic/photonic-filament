<?php

use Digitonic\Mediatonic\Filament\Models\Media;

return [
    // Base URL of the third-party API that processes the uploaded image and
    // returns a filename to be stored in your application's database.
    // You can override this in your app's config or via MEDIATONIC_ENDPOINT env var.
    'endpoint' => env('MEDIATONIC_ENDPOINT', 'https://mediatonic.test/api/v1'),
    'cdn_endpoint' => env('MEDIATONIC_CDN_ENDPOINT', 'https://minio.herd.test/mediatonic'),

    'site_uuid' => env('MEDIATONIC_SITE_UUID'),
    'api_key' => env('MEDIATONIC_API_KEY'),

    // The multipart field name the API expects for the uploaded file.
    'file_field' => env('MEDIATONIC_FILE_FIELD', 'file'),

    // The response key that contains the returned filename. If null, the field will
    // attempt to parse the entire response body as the filename. If set, we'll try this
    // key first and then fall back to common alternatives.
    'response_key' => env('MEDIATONIC_RESPONSE_KEY', 'original_filename'),

    // The table to record uploads to.
    'media_model' => Media::class,
];
