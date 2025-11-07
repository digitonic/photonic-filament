@props([
    'filename' => null,
    'preset' => 'featured',
    'alt' => null,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    if (!$filename) {
        return;
    }

    $cdn = rtrim(config('mediatonic.cdn_endpoint'), '/');
    $site = trim(config('mediatonic.site_uuid'), '/');
    $rawFilename = ltrim($filename, '/');

    // Parse "mediaUuid/filename.ext" or just "mediaUuid"
    [$parsedMediaUuid, $filePart] = array_pad(explode('/', $rawFilename, 2), 2, '');

    // Use explicit mediaUuid prop if provided, otherwise use parsed
    $uuid = $media->uuid ?? $parsedMediaUuid;

    // Build URL based on preset type
    if (strtolower(trim($preset)) === 'originals') {
        $src = sprintf(
            '%s/%s/%s/original/%s',
            $cdn,
            $site,
            $uuid,
            ltrim($filePart, '/')
        );
    } else {
        // Preset path: replace extension with .webp
        $baseFilename = pathinfo($filePart, PATHINFO_FILENAME);
        $webpFilename = $baseFilename . '.webp';

        $src = sprintf(
            '%s/%s/%s/presets/%s/%s',
            $cdn,
            $site,
            $uuid,
            trim($preset, '/'),
            $webpFilename
        );
    }

    $classes = $attributes->has('class') ? $attributes->get('class') : $class;
    $altText = $alt ?? $filename;
@endphp

<img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $classes }}" />
