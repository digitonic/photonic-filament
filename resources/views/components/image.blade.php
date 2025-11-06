@props([
    // Required filename to render (string). If null/empty, nothing is rendered.
    'filename' => null,

    // Preset path segment to use in the CDN URL, defaults to 'featured'.
    'preset' => 'featured',

    // Alt text override; defaults to the filename if not provided.
    'alt' => null,

    // Default classes; can be overridden by passing a class attribute on the component
    // or by setting this prop explicitly.
    'class' => 'object-cover w-auto',
])

@php
    $filename = $filename ? ltrim((string) $filename, '/') : null;
@endphp

@if ($filename)
    @php
        $cdn = (string) config('mediatonic.cdn_endpoint');
        $site = (string) config('mediatonic.site_uuid');

        $presetSegment = trim((string) $preset, '/');
        $src = rtrim($cdn, '/')
            . '/' . trim($site, '/')
            . ($presetSegment !== '' ? '/' . $presetSegment : '')
            . '/' . $filename;

        // Allow hard override of default classes when a class attribute is passed
        $classes = $attributes->has('class') ? $attributes->get('class') : $class;
        $altText = $alt ?? $filename;
    @endphp

    <img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $classes }}" />
@endif
