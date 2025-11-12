@use(Digitonic\MediaTonic\Filament\Enums\PresetEnum)

@props([
    'preset' => PresetEnum::ORIGINAL->value,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    $src = mediatonic_asset(
            filename:  $media->filename,
            assetUuid: $media->asset_uuid,
            preset: $preset
        );

    $classes = $attributes->has('class') ? $attributes->get('class') : $class;
    // Prioritize media.alt over passed alt over filename
    $altText = $media->alt ?? $media->filename;
@endphp

<img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $classes }}" />
