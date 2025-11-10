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

if (! function_exists('get_mediatonic_table_name')) {
    function get_mediatonic_table_name(): string
    {
        $model = config('mediatonic-filament.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);

        return (new $model)->getTable();
    }
}
