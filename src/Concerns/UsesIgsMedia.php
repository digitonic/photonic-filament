<?php

namespace Digitonic\Filament\IgsField\Concerns;

use Digitonic\Filament\IgsField\Models\IgsMedia;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait UsesIgsMedia
{
    /**
     * Polymorphic one-to-many relation to IgsMedia records.
     */
    public function igsMedia(): MorphOne
    {
        return $this->morphOne(IgsMedia::class, name: 'model', type: 'model_type', id: 'model_id');
    }

    /**
     * Convenience helper to attach a new media row to this model.
     */
    public function addIgsMedia(string $filename, ?array $presets = null): IgsMedia
    {
        return $this->igsMedia()->create([
            'filename' => $filename,
            'presets' => $presets,
        ]);
    }

    /**
     * Remove a media record (by id) that belongs to this model.
     */
    public function removeIgsMedia(int $mediaId): bool
    {
        return (bool) $this->igsMedia()->whereKey($mediaId)->delete();
    }
}
