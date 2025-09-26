<?php

return [
    // Base URL of the third-party API that processes the uploaded image and
    // returns a filename to be stored in your application's database.
    // You can override this in your app's config or via FILAMENT_LUME_ENDPOINT env var.
    'endpoint' => env('FILAMENT_LUME_ENDPOINT', 'https://lume.test/api'),
    'cdn_endpoint' => env('FILAMENT_LUME_CDN_ENDPOINT', 'https://minio.herd.test/lume'),

    'site_uuid' => env('FILAMENT_LUME_SITE_UUID'),
    'api_key' => env('FILAMENT_LUME_API_KEY'),

    // The multipart field name the API expects for the uploaded file.
    'file_field' => env('FILAMENT_LUME_FILE_FIELD', 'file'),

    // The response key that contains the returned filename. If null, the field will
    // attempt to parse the entire response body as the filename. If set, we'll try this
    // key first and then fall back to common alternatives.
    'response_key' => env('FILAMENT_LUME_RESPONSE_KEY', 'filename'),

    // Whether the field should record uploads automatically.
    'record_uploads' => env('FILAMENT_LUME_RECORD_UPLOADS', true),

    // The table to record uploads to.
    'media_table' => env('FILAMENT_LUME_MEDIA_TABLE', 'lume_media'),
];
