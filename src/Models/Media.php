<?php

namespace Digitonic\MediaTonic\Filament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $filename
 * @property string|null $model_type
 * @property string $asset_uuid
 * @property string|null $model_id
 * @property string|null $alt
 * @property string|null $title
 * @property string|null $description
 * @property string|null $caption
 * @property array|null $config
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
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
            'config' => 'json',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo(name: 'model', type: 'model_type', id: 'model_id');
    }

    /**
     * Get the full CDN URL for this media asset.
     */
    public function getUrl(string $preset = 'original'): ?string
    {
        return mediatonic_asset(
            filename: $this->filename,
            assetUuid: $this->asset_uuid,
            preset: $preset
        );
    }

    /**
     * Boot the model and register event listeners.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Clear cache when media is updated
        static::updated(function (Media $media) {
            forget_mediatonic_cache($media->id);
        });

        // Clear cache when media is deleted
        static::deleted(function (Media $media) {
            forget_mediatonic_cache($media->id);
        });
    }
}
