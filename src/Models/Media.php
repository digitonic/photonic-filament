<?php

namespace Digitonic\Photonic\Filament\Models;

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
    protected $table = 'photonic';
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

    public function getUrl(string $preset = 'original'): ?string
    {
        return photonic_asset(
            filename: $this->filename,
            assetUuid: $this->asset_uuid,
            preset: $preset
        );
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updated(function (Media $media) {
            forget_photonic_cache($media->id);
        });

        static::deleted(function (Media $media) {
            forget_photonic_cache($media->id);
        });
    }
}
