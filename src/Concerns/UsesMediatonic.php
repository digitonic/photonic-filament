<?php

namespace Digitonic\MediaTonic\Filament\Concerns;

use Digitonic\MediaTonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @mixin \Illuminate\Database\Eloquent\Model
 * @property-read \Digitonic\MediaTonic\Filament\Models\Media|null $mediaTonicMedia
 */
trait UsesMediaTonic
{
    public function mediaTonicMedia(): MorphOne
    {
        $model = config('mediatonic-filament.media_model', \Digitonic\MediaTonic\Filament\Models\Media::class);

        return $this->morphOne($model, name: 'model', type: 'model_type', id: 'model_id');
    }

    /**
     * Convenience helper to attach a new media row to this model.
     */
    public function addMediaTonicMedia(
        string $filename,
        ?array $presets = null,
        ?string $alt = null,
        ?string $title = null,
        ?string $description = null,
        ?string $caption = null
    ): Media {
        return $this->mediaTonicMedia()->create([
            'filename' => $filename,
            'presets' => $presets,
            'alt' => $alt,
            'title' => $title,
            'description' => $description,
            'caption' => $caption,
        ]);
    }

    /**
     * Remove a media record (by id) that belongs to this model.
     */
    public function removeMediaTonicMedia(int $mediaId): bool
    {
        return (bool) $this->mediaTonicMedia()->whereKey($mediaId)->delete();
    }

    /**
     * Get a media record by its ID.
     * Useful when media IDs are stored in JSON fields on the model.
     */
    public static function getMediaTonicById(int $mediaId): ?Media
    {
        $model = config('mediatonic-filament.media_model', \Digitonic\MediaTonic\Filament\Models\Media::class);

        return $model::find($mediaId);
    }

    /**
     * Get multiple media records by their IDs.
     * Useful when multiple media IDs are stored in a JSON field on the model.
     *
     * @param  array<int>|null  $mediaIds
     * @return Collection<int, Media>
     */
    public static function getMediaTonicByIds(?array $mediaIds): Collection
    {
        $model = config('mediatonic-filament.media_model', \Digitonic\MediaTonic\Filament\Models\Media::class);

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
    public function getMediaTonicFromAttribute(string $attribute): Media|Collection|null
    {
        $value = $this->getAttribute($attribute);

        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return static::getMediaTonicByIds($value);
        }

        return static::getMediaTonicById((int) $value);
    }
}
