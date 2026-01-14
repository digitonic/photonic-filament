@use(Digitonic\\Photonic\\Filament\\Enums\\PresetEnum)

@props([
    'preset' => PresetEnum::ORIGINAL->value,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    if($media === null) {
        return;
    }
    $src = photonic_asset(
            filename:  $media->filename,
            assetUuid: $media->asset_uuid,
            preset: $preset
        );
    // Prioritize media.alt over passed alt over filename
    $altText = $media->alt ?? $media->filename;
@endphp

<img src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $class }}" />
