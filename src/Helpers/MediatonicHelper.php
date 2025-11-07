<?php


if (! function_exists('mediatonic_asset')) {
    /**
     * Parse and return a URL structure that should point to an uploaded asset.
     *
     * @param string $filename
     * @param string $preset
     * @return string|null
     */
    function mediatonic_asset(string $filename, string $preset = 'originals'): ?string
    {
        $cdn = (string) config('mediatonic.cdn_endpoint');
        $site = (string) config('mediatonic.site_uuid');

        $presetSegment = trim($preset, '/');
        return rtrim($cdn, '/')
            . '/' . trim($site, '/')
            . ($presetSegment !== '' ? '/' . $presetSegment : '')
            . '/' . $filename;
    }
}

if (! function_exists('get_mediatonic_table_name')) {
    function get_mediatonic_table_name(): string
    {
        $model = config('mediatonic.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);
        return (new $model())->getTable();
    }
}
