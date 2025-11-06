<?php

namespace Digitonic\Filament\Lume\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    /**
     * Use the configured table name, defaulting to 'igs_media'.
     */
    public function getTable(): string
    {
        return (string) config('filament-lume.media_table', 'lume_media');
    }

    /**
     * Guard nothing to allow relation create() to fill keys and attributes.
     */
    protected $guarded = [];

    protected $casts = [
        'presets' => 'array',
    ];

    /**
     * Inverse morph relation to the owning model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo(name: 'model', type: 'model_type', id: 'model_id');
    }
}
