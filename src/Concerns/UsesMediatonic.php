<?php

namespace Digitonic\Mediatonic\Filament\Concerns;

use Digitonic\Mediatonic\Filament\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait UsesMediatonic
{
    public function mediatonicMedia(): MorphOne
    {
        $model = config('mediatonic.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);

        return $this->morphOne($model, name: 'model', type: 'model_type', id: 'model_id');
    }

    /**
     * Convenience helper to attach a new media row to this model.
     */
    public function addMediatonicMedia(string $filename, ?array $presets = null): Media
    {
        return $this->mediatonicMedia()->create([
            'filename' => $filename,
            'presets' => $presets,
        ]);
    }

    /**
     * Remove a media record (by id) that belongs to this model.
     */
    public function removeMediatonicMedia(int $mediaId): bool
    {
        return (bool) $this->mediatonicMedia()->whereKey($mediaId)->delete();
    }
}
