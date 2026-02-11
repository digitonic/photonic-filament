<?php

use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Facades\Photonic;
use Digitonic\Photonic\Filament\Models\Media;

if (! function_exists('photonic_asset')) {
    /**
     * Parse and return a URL structure that should point to an uploaded asset.
     */
    function photonic_asset(string $filename, string $assetUuid, string $preset = 'original'): ?string
    {
        if ($filename === '') {
            return null;
        }

        $cdn = rtrim((string) config('photonic-filament.cdn_endpoint'), '/');
        $site = trim((string) config('photonic-filament.site_uuid'), '/');
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

if (! function_exists('photonic_asset_by_id')) {
    /**
     * Get the CDN URL for a media asset by its ID.
     * This function caches the result to avoid repeated database queries.
     *
     * @param  int  $mediaId  The ID of the media record
     * @param  string  $preset  The preset to use (e.g. 'original', 'thumbnail', 'featured')
     * @param  int  $cacheTtl  Cache duration in seconds (default: 3600 = 1 hour)
     * @return string|null The CDN URL or null if media not found
     */
    function photonic_asset_by_id(int $mediaId, string $preset = 'original', int $cacheTtl = 3600): ?string
    {
        $cacheKey = "photonic_asset_{$mediaId}_{$preset}";

        return cache()->remember($cacheKey, $cacheTtl, fn () => Photonic::make()
            ->for($mediaId)
            ->preset($preset)
            ->cacheTtl($cacheTtl)
            ->url());
    }
}

if (! function_exists('photonic_media_by_id')) {
    /**
     * Get the full media model by its ID with caching.
     * This is useful when you need access to metadata (alt, title, description, caption).
     *
     * @param  int  $mediaId  The ID of the media record
     * @param  int  $cacheTtl  Cache duration in seconds (default: 3600 = 1 hour)
     */
    function photonic_media_by_id(int $mediaId, int $cacheTtl = 3600): ?Media
    {
        return Photonic::make()
            ->for($mediaId)
            ->cacheTtl($cacheTtl)
            ->media();
    }
}

if (! function_exists('forget_photonic_cache')) {
    /**
     * Clear the cached data for a specific media ID.
     * Call this when updating or deleting media records.
     *
     * @param  int  $mediaId  The ID of the media record
     */
    function forget_photonic_cache(int $mediaId): void
    {
        // Clear the media model cache
        cache()->forget("photonic_media_{$mediaId}");

        // Clear all preset URL caches for this media
        // Note: We can't know all presets used, so we clear common ones
        $commonPresets = ['original', 'thumbnail', 'featured', 'banner', 'small', 'medium', 'large'];
        foreach ($commonPresets as $preset) {
            cache()->forget("photonic_asset_{$mediaId}_{$preset}");
        }
    }
}

if (! function_exists('get_photonic_table_name')) {
    function get_photonic_table_name(): string
    {
        /** @var class-string<Media> $model */
        $model = config('photonic-filament.media_model', Media::class);

        return (new $model)->getTable();
    }
}
