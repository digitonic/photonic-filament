<?php

namespace Digitonic\Filament\IgsField\Forms\Components;

use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class IgsInput extends FileUpload
{
    protected ?string $igsEndpoint = null;
    protected ?string $igsFileField = null;
    protected ?string $igsResponseKey = null;
    protected ?bool $igsRecordUploads = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Treat as image upload by default
        $this->image();

        // Intercept the save process to send the file to the IGS API and
        // store the returned filename in the field state / database.
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
            $endpoint = $this->igsEndpoint ?? config('igs-field.endpoint');
            $fileField = $this->igsFileField ?? config('igs-field.file_field', 'file');
            $responseKey = $this->igsResponseKey ?? config('igs-field.response_key', 'filename');

            if (blank($endpoint)) {
                throw new \RuntimeException('IGS endpoint is not configured. Set igs-field.endpoint in your config.');
            }

            $stream = Storage::readStream($file->getRealPath());

            try {
                $response = Http::asMultipart()
                    ->attach($fileField, $stream, $file->getClientOriginalName())
                    ->post($endpoint . '/upload/' . config('igs-field.site_uuid'));
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Throw on HTTP errors
            $response->throw();

            // Parse JSON response when possible
            $json = $response->json();

            // Determine the filename to return
            $filename = null;
            if (is_array($json)) {
                if ($responseKey && array_key_exists($responseKey, $json)) {
                    $filename = (string) $json[$responseKey];
                } else {
                    foreach (['filename', 'file', 'name'] as $key) {
                        if (array_key_exists($key, $json)) {
                            $filename = (string) $json[$key];
                            break;
                        }
                    }
                }
            }

            dump('filename, check', $filename);

            if ($filename === null) {
                // Fallback to raw body
                $body = trim((string) $response->body());
                if ($body !== '') {
                    $filename = $body;
                }
            }

            if ($filename === null) {
                throw new \RuntimeException('IGS endpoint response did not contain a filename.');
            }

            // Optionally record the upload in the igs_media table
            $shouldRecord = $this->igsRecordUploads ?? (bool) config('igs-field.record_uploads', true);
            if ($shouldRecord) {
                $this->recordUpload($filename, is_array($json) ? $json : null);
            }

            return $filename;
        });
    }

    /**
     * Override API endpoint per field instance.
     */
    public function endpoint(?string $endpoint): static
    {
        $this->igsEndpoint = $endpoint;

        return $this;
    }

    /**
     * Override the multipart file field name per field instance.
     */
    public function fileField(string $name): static
    {
        $this->igsFileField = $name;

        return $this;
    }

    /**
     * Override the response key used to extract the filename per field instance.
     */
    public function responseKey(?string $key): static
    {
        $this->igsResponseKey = $key;

        return $this;
    }

    /**
     * Enable/disable recording uploads to the igs_media table for this field instance.
     */
    public function recordToMedia(bool $enabled = true): static
    {
        $this->igsRecordUploads = $enabled;

        return $this;
    }

    /**
     * Insert a row into the igs_media table if model context is available.
     */
    protected function recordUpload(string $filename, ?array $jsonResponse = null): void
    {
        try {
            $modelClass = $this->getModel();
            $modelId = $this->resolveCurrentRecordId();

            if (! $modelClass || ! $modelId) {
                // No model context; skip recording.
                return;
            }

            $presets = null;
            if (is_array($jsonResponse)) {
                if (array_key_exists('presets', $jsonResponse)) {
                    $presets = $jsonResponse['presets'];
                } elseif (array_key_exists('preset', $jsonResponse)) {
                    $presets = $jsonResponse['preset'];
                }
            }

            DB::table((string) config('igs-field.media_table', 'igs_media'))->insert([
                'model_type' => $modelClass,
                'model_id' => $modelId,
                'filename' => $filename,
                'presets' => $presets !== null ? json_encode($presets) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Do not block the upload on recording failure; consider logging if app has logger
            if (function_exists('logger')) {
                logger()->warning('IGS field failed to record upload to media table', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Best-effort resolution of the current record ID from the Livewire component.
     */
    protected function resolveCurrentRecordId(): ?int
    {
        $livewire = $this->getLivewire();

        // Method-based API
        if (method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();
            if ($record && method_exists($record, 'getKey')) {
                return (int) $record->getKey();
            }
        }

        // Common public properties used in Filament components
        foreach (['record', 'ownerRecord'] as $prop) {
            if (property_exists($livewire, $prop)) {
                $record = $livewire->{$prop};
                if ($record && method_exists($record, 'getKey')) {
                    return (int) $record->getKey();
                }
            }
        }

        // Try to get the dehydrated state if the model key is part of the form state
        if (method_exists($livewire, 'form')) {
            try {
                $state = $livewire->form->getState();
                foreach (['id', $this->getStatePath().'.id'] as $key) {
                    if (is_array($state) && array_key_exists($key, $state) && $state[$key]) {
                        return (int) $state[$key];
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }
}
