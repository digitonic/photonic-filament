@props([
    'filename' => null,
    'preset' => 'original',
    'alt' => null,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    $src = mediatonic_asset($filename, $preset)

    $classes = $attributes->has('class') ? $attributes->get('class') : $class;
    $altText = $alt ?? $filename;
@endphp

<img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $classes }}" />
