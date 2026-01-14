<?php

namespace Digitonic\Photonic\Filament\Concerns;

use Digitonic\Photonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property-read Media|null $photonicMedia
 */
trait UsesPhotonic
{
    public function photonicMedia(): MorphOne
    {
        /** @var class-string<Media> $model */
        $model = config('photonic-filament.media_model', Media::class);

        return $this->morphOne($model, name: 'model', type: 'model_type', id: 'model_id');
    }

    public function addPhotonicMedia(
        string $filename,
        ?array $presets = null,
        ?string $alt = null,
        ?string $title = null,
        ?string $description = null,
        ?string $caption = null
    ): Media {
        return $this->photonicMedia()->create([
            'filename' => $filename,
            'presets' => $presets,
            'alt' => $alt,
            'title' => $title,
            'description' => $description,
            'caption' => $caption,
        ]);
    }

    public function removePhotonicMedia(int $mediaId): bool
    {
        return (bool) $this->photonicMedia()->whereKey($mediaId)->delete();
    }

    public static function getPhotonicById(int $mediaId): ?Media
    {
        /** @var class-string<Media> $model */
        $model = config('photonic-filament.media_model', Media::class);

        return $model::find($mediaId);
    }

    /**
     * @param  array<int>|null  $mediaIds
     * @return Collection<int, Media>
     */
    public static function getPhotonicByIds(?array $mediaIds): Collection
    {
        /** @var class-string<Media> $model */
        $model = config('photonic-filament.media_model', Media::class);

        if (empty($mediaIds)) {
            return new Collection;
        }

        return $model::whereIn('id', $mediaIds)->get();
    }

    /**
     * Get media from a JSON attribute on this model that contains media ID(s).
     * Supports both single ID (int) and array of IDs.
     *
     * @return Media|Collection<int, Media>|null
     */
    public function getPhotonicFromAttribute(string $attribute): Media|Collection|null
    {
        $value = $this->getAttribute($attribute);

        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return static::getPhotonicByIds($value);
        }

        return static::getPhotonicById((int) $value);
    }
}
