<?php

namespace Digitonic\Filament\IgsField\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IgsMedia extends Model
{
    /**
     * Use the configured table name, defaulting to 'igs_media'.
     */
    public function getTable(): string
    {
        return (string) config('igs-field.media_table', 'igs_media');
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
