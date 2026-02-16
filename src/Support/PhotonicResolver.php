<?php

namespace Digitonic\Photonic\Filament\Support;

use Digitonic\Photonic\Filament\Data\PhotonicInfo;
use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Models\Media;

class PhotonicResolver
{
    private Media|int|null $target = null;

    private string $preset = PresetEnum::ORIGINAL->value;

    private int $cacheTtl = 3600;

    public function for(Media|int $media): self
    {
        $this->target = $media;

        return $this;
    }

    public function preset(string|PresetEnum $preset): self
    {
        $this->preset = $preset instanceof PresetEnum
            ? $preset->value
            : $preset;

        return $this;
    }

    public function cacheTtl(int $seconds): self
    {
        $this->cacheTtl = $seconds;

        return $this;
    }

    public function media(): ?Media
    {
        if ($this->target instanceof Media) {
            return $this->target;
        }

        if (! is_int($this->target)) {
            return null;
        }

        $cacheKey = "photonic_media_{$this->target}";
        $mediaId = $this->target;

        return cache()->remember($cacheKey, $this->cacheTtl, function () use ($mediaId) {
            /** @var class-string<Media> $mediaModelClass */
            $mediaModelClass = config('photonic-filament.media_model', Media::class);

            return $mediaModelClass::find($mediaId);
        });
    }

    public function url(): ?string
    {
        $media = $this->media();

        if (! $media) {
            return null;
        }

        return photonic_asset(
            filename: $media->filename,
            assetUuid: $media->asset_uuid,
            preset: $this->preset
        );
    }

    public function info(): ?PhotonicInfo
    {
        $media = $this->media();

        if (! $media) {
            return null;
        }

        return new PhotonicInfo(
            id: $media->id,
            assetUuid: $media->asset_uuid,
            filename: $media->filename,
            preset: $this->preset,
            url: $this->url(),
            alt: $media->alt,
            title: $media->title,
            description: $media->description,
            caption: $media->caption,
            config: $media->config,
            createdAt: $media->created_at?->toISOString(),
            updatedAt: $media->updated_at?->toISOString(),
        );
    }
}
