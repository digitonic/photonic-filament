<?php

namespace Digitonic\Photonic\Filament\Console;

use Digitonic\Photonic\Filament\Support\EnvFileEditor;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'photonic-filament:install
        {--endpoint= : Photonic API base URL}
        {--cdn-endpoint= : Photonic CDN base URL}
        {--site-uuid= : Photonic site UUID}
        {--api-key= : Photonic API key (Bearer token)}
        {--file-field= : Multipart field name (defaults to file)}
        {--response-key= : Response key (defaults to original_filename)}
        {--force : Overwrite existing env values}';

    protected $description = 'Install and configure the Photonic Filament package.';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $defaults = config('photonic-filament');

        $editor = new EnvFileEditor();
        $envPath = base_path('.env');

        $values = [
            'PHOTONIC_ENDPOINT' => $this->resolveValue('endpoint', 'PHOTONIC_ENDPOINT', $defaults['endpoint'] ?? null, false, $force, $editor, $envPath, 'Photonic API endpoint URL'),
            'PHOTONIC_CDN_ENDPOINT' => $this->resolveValue('cdn-endpoint', 'PHOTONIC_CDN_ENDPOINT', $defaults['cdn_endpoint'] ?? null, false, $force, $editor, $envPath, 'Photonic CDN endpoint URL'),
            'PHOTONIC_SITE_UUID' => $this->resolveValue('site-uuid', 'PHOTONIC_SITE_UUID', $defaults['site_uuid'] ?? null, false, $force, $editor, $envPath, 'Photonic site UUID'),
            'PHOTONIC_API_KEY' => $this->resolveValue('api-key', 'PHOTONIC_API_KEY', null, true, $force, $editor, $envPath, 'Photonic API key (Bearer token)'),
            'PHOTONIC_FILE_FIELD' => $this->resolveValue('file-field', 'PHOTONIC_FILE_FIELD', $defaults['file_field'] ?? 'file', false, $force, $editor, $envPath, 'Upload multipart field name'),
            'PHOTONIC_RESPONSE_KEY' => $this->resolveValue('response-key', 'PHOTONIC_RESPONSE_KEY', $defaults['response_key'] ?? 'original_filename', false, $force, $editor, $envPath, 'Response key for returned filename'),
        ];

        $missingRequired = [];
        foreach (['PHOTONIC_ENDPOINT', 'PHOTONIC_CDN_ENDPOINT', 'PHOTONIC_SITE_UUID', 'PHOTONIC_API_KEY'] as $required) {
            if (blank($values[$required] ?? null)) {
                $missingRequired[] = $required;
            }
        }

        if (! empty($missingRequired)) {
            $this->error('Missing required values: '.implode(', ', $missingRequired));
            return self::FAILURE;
        }

        $envExamplePath = base_path('.env.example');

        $this->newLine();
        $this->info('Writing environment variables to .env and .env.example...');

        // .env: write real values.
        $editor->upsert($envPath, $values, $force);

        // .env.example: write placeholders; never put the real API key in here.
        $exampleValues = $values;
        $exampleValues['PHOTONIC_API_KEY'] = '';
        $editor->upsert($envExamplePath, $exampleValues, $force);

        $this->newLine();
        $this->info('Photonic Filament installed.');

        if (
            $this->input->isInteractive()
            && ! (bool) $this->option('no-interaction')
            && $this->confirm('Do you want to publish config, views, and migrations now?', true)
        ) {
            $this->call('vendor:publish', [
                '--provider' => 'Digitonic\\Photonic\\Filament\\PhotonicServiceProvider',
                '--tag' => ['photonic-filament-config', 'photonic-filament-views', 'photonic-filament-migrations'],
            ]);
        }

        return self::SUCCESS;
    }

    private function resolveValue(
        string $optionKey,
        string $envKey,
        ?string $default,
        bool $secret,
        bool $force,
        EnvFileEditor $editor,
        string $envPath,
        string $label,
    ): ?string {
        // If provided via CLI option, always prefer it.
        $opt = $this->option($optionKey);
        if ($opt !== null) {
            return trim((string) $opt);
        }

        // If not forcing and value already exists in .env, keep it.
        if (! $force) {
            $existing = $editor->get($envPath, $envKey);
            if (filled($existing)) {
                return $existing;
            }
        }

        // If we can't prompt, fall back to default (if any).
        if (! $this->input->isInteractive() || (bool) $this->option('no-interaction')) {
            return $default;
        }

        $value = $secret
            ? $this->secret($label)
            : $this->ask($label, $default);

        return $value === null ? $default : trim((string) $value);
    }
}
