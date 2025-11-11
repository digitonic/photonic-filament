<?php

use Digitonic\Mediatonic\Filament\Enums\PresetEnum;

if (! function_exists('mediatonic_asset')) {
    /**
     * Parse and return a URL structure that should point to an uploaded asset.
     */
    function mediatonic_asset(string $filename, string $assetUuid, string $preset = 'original'): ?string
    {
        if ($filename === '') {
            return null;
        }

        $cdn = rtrim((string) config('mediatonic-filament.cdn_endpoint'), '/');
        $site = trim((string) config('mediatonic-filament.site_uuid'), '/');
        $presetSegment = trim($preset, '/');

        if (strtolower($presetSegment) === PresetEnum::ORIGINAL->value) {
            return sprintf(
                '%s/%s/%s/original/%s',
                $cdn,
                $site,
                $assetUuid,
                ltrim($filename, '/')
            );
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $webpFilename = $base.'.webp';

        return sprintf(
            '%s/%s/%s/presets/%s/%s',
            $cdn,
            $site,
            $assetUuid,
            $presetSegment,
            $webpFilename
        );
    }
}

if (! function_exists('mediatonic_asset_by_id')) {
    /**
     * Get the CDN URL for a media asset by its ID.
     * This function caches the result to avoid repeated database queries.
     *
     * @param  int  $mediaId  The ID of the media record
     * @param  string  $preset  The preset to use (e.g. 'original', 'thumbnail', 'featured')
     * @param  int  $cacheTtl  Cache duration in seconds (default: 3600 = 1 hour)
     * @return string|null The CDN URL or null if media not found
     */
    function mediatonic_asset_by_id(int $mediaId, string $preset = 'original', int $cacheTtl = 3600): ?string
    {
        $cacheKey = "mediatonic_asset_{$mediaId}_{$preset}";

        return cache()->remember($cacheKey, $cacheTtl, function () use ($mediaId, $preset) {
            $mediaModelClass = config('mediatonic-filament.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);
            $media = $mediaModelClass::find($mediaId);

            if (! $media) {
                return null;
            }

            return mediatonic_asset(
                filename: $media->filename,
                assetUuid: $media->asset_uuid,
                preset: $preset
            );
        });
    }
}

if (! function_exists('mediatonic_media_by_id')) {
    /**
     * Get the full media model by its ID with caching.
     * This is useful when you need access to metadata (alt, title, description, caption).
     *
     * @param  int  $mediaId  The ID of the media record
     * @param  int  $cacheTtl  Cache duration in seconds (default: 3600 = 1 hour)
     * @return \Digitonic\Mediatonic\Filament\Models\Media|null
     */
    function mediatonic_media_by_id(int $mediaId, int $cacheTtl = 3600): ?\Digitonic\Mediatonic\Filament\Models\Media
    {
        $cacheKey = "mediatonic_media_{$mediaId}";

        return cache()->remember($cacheKey, $cacheTtl, function () use ($mediaId) {
            $mediaModelClass = config('mediatonic-filament.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);
            return $mediaModelClass::find($mediaId);
        });
    }
}

if (! function_exists('forget_mediatonic_cache')) {
    /**
     * Clear the cached data for a specific media ID.
     * Call this when updating or deleting media records.
     *
     * @param  int  $mediaId  The ID of the media record
     * @return void
     */
    function forget_mediatonic_cache(int $mediaId): void
    {
        // Clear the media model cache
        cache()->forget("mediatonic_media_{$mediaId}");

        // Clear all preset URL caches for this media
        // Note: We can't know all presets used, so we clear common ones
        $commonPresets = ['original', 'thumbnail', 'featured', 'banner', 'small', 'medium', 'large'];
        foreach ($commonPresets as $preset) {
            cache()->forget("mediatonic_asset_{$mediaId}_{$preset}");
        }
    }
}

if (! function_exists('get_mediatonic_table_name')) {
    function get_mediatonic_table_name(): string
    {
        $model = config('mediatonic-filament.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);

        return (new $model)->getTable();
    }
}
