<?php

use Digitonic\Photonic\Filament\Models\Media;

return [
    // Base URL of the third-party API.
    'endpoint' => env('PHOTONIC_ENDPOINT'),
    'cdn_endpoint' => env('PHOTONIC_CDN_ENDPOINT'),
    'site_uuid' => env('PHOTONIC_SITE_UUID'),
    'api_key' => env('PHOTONIC_API_KEY'),

    // The multipart field name the API expects for the uploaded file.
    'file_field' => env('PHOTONIC_FILE_FIELD', 'file'),

    // The response key that contains the returned filename.
    'response_key' => env('PHOTONIC_RESPONSE_KEY', 'original_filename'),

    // The model used to persist uploads.
    'media_model' => Media::class,
];
