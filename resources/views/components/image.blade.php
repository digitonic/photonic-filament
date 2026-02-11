@props([
    'preset' => Digitonic\Photonic\Filament\Enums\PresetEnum::ORIGINAL->value,
    'class' => 'object-cover w-auto',
    'media' => null,
])

@php
    if($media === null) {
        return;
    }
    $src = \Digitonic\Photonic\Filament\Facades\Photonic::make()
        ->for($media)
        ->preset($preset)
        ->url();
    // Prioritize media.alt over passed alt over filename
    $altText = $media->alt ?? $media->filename;
@endphp

<img fetchpriority=high src="{{ $src }}" alt="{{ $altText }}" {{ $attributes->except('class') }} class="{{ $class }}" />
