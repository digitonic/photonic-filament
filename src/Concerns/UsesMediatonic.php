<?php

namespace Digitonic\MediaTonic\Filament\Concerns;

use Digitonic\MediaTonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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
}
