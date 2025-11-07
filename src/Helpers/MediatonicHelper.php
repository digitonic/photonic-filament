<?php


use Digitonic\Mediatonic\Filament\Enums\PresetEnum;

if (! function_exists('mediatonic_asset')) {
    /**
     * Parse and return a URL structure that should point to an uploaded asset.
     *
     * @param string $filename
     * @param string $preset
     * @return string|null
     */
    function mediatonic_asset(string $filename, string $preset = 'original'): ?string
    {
        if ($filename === '') {
            return null;
        }

        $cdn = rtrim((string) config('mediatonic.cdn_endpoint'), '/');
        $site = trim((string) config('mediatonic.site_uuid'), '/');
        $presetSegment = trim($preset, '/');

        if (strtolower($presetSegment) === PresetEnum::ORIGINAL->value) {
            return sprintf(
                '%s/%s/original/%s',
                $cdn,
                $site,
                ltrim($filename, '/')
            );
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $webpFilename = $base . '.webp';

        return sprintf(
            '%s/%s/presets/%s/%s',
            $cdn,
            $site,
            $presetSegment,
            $webpFilename
        );
    }
}

if (! function_exists('get_mediatonic_table_name')) {
    function get_mediatonic_table_name(): string
    {
        $model = config('mediatonic.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);
        return (new $model())->getTable();
    }
}
