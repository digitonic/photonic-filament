<?php

namespace Digitonic\Filament\Lume\Concerns;

use Digitonic\Filament\Lume\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait UsesLumeMedia
{
    /**
     * Polymorphic one-to-many relation to Lume Media records.
     */
    public function lumeMedia(): MorphOne
    {
        return $this->morphOne(Media::class, name: 'model', type: 'model_type', id: 'model_id');
    }

    /**
     * Convenience helper to attach a new media row to this model.
     */
    public function addLumeMedia(string $filename, ?array $presets = null): Media
    {
        return $this->lumeMedia()->create([
            'filename' => $filename,
            'presets' => $presets,
        ]);
    }

    /**
     * Remove a media record (by id) that belongs to this model.
     */
    public function removeLumeMedia(int $mediaId): bool
    {
        return (bool) $this->lumeMedia()->whereKey($mediaId)->delete();
    }
}
