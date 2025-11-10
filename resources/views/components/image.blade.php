@use(Digitonic\Mediatonic\Filament\Enums\PresetEnum)

@props([
    'filename' => null,
    'preset' => PresetEnum::ORIGINAL->value,
    'alt' => null,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    $src = mediatonic_asset(
            filename: $filename,
            assetUuid: $media->asset_uuid,
            preset: $preset
        );

    $classes = $attributes->has('class') ? $attributes->get('class') : $class;
    $altText = $alt ?? $filename;
@endphp

<img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $classes }}" />
