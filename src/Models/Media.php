<?php

namespace Digitonic\Mediatonic\Filament\Models;


use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    /**
     * Use the configured table name for the media model.
     *  If you want to change the table name, extend this model and override the getTable method.
     *  */
    public function getTable(): string
    {
        return 'mediatonic';
    }

    /**
     * Guard nothing to allow relation create() to fill keys and attributes.
     */
    protected $guarded = [];

    public function casts(): array
    {
        return [
            'presets' => 'array',
            'config' => 'array',
        ];
    }

    /**
     * Inverse morph relation to the owning model.
     */
    public function model(): MorphTo
    {
        return $this->morphTo(name: 'model', type: 'model_type', id: 'model_id');
    }
}
