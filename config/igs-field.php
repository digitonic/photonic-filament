<?php

return [
    // Base URL of the third-party API that processes the uploaded image and
    // returns a filename to be stored in your application's database.
    // You can override this in your app's config or via IGS_FIELD_ENDPOINT env var.
    'endpoint' => env('IGS_FIELD_ENDPOINT', 'https://igs.test/api'),
    'cdn_endpoint' => env('IGS_FIELD_CDN_ENDPOINT', 'https://minio.herd.test/igs'),

    'site_uuid' => env('IGS_FIELD_SITE_UUID'),
    'api_key' => env('IGS_FIELD_API_KEY'),

    // The multipart field name the API expects for the uploaded file.
    'file_field' => env('IGS_FIELD_FILE_FIELD', 'file'),

    // The response key that contains the returned filename. If null, the field will
    // attempt to parse the entire response body as the filename. If set, we'll try this
    // key first and then fall back to common alternatives.
    'response_key' => env('IGS_FIELD_RESPONSE_KEY', 'filename'),

    // Whether the field should record uploads to the igs_media table automatically.
    'record_uploads' => env('IGS_FIELD_RECORD_UPLOADS', true),

    // The table to record uploads to.
    'media_table' => env('IGS_FIELD_MEDIA_TABLE', 'igs_media'),
];
