<?php

namespace Digitonic\Mediatonic\Filament\Forms\Components;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API;
use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests\CreateAsset;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use function get_mediatonic_table_name;

class MediatonicInput extends FileUpload
{
    protected ?string $endpoint = null;

    protected ?string $fileField = null;

    protected ?string $responseKey = null;

    protected ?bool $recordUploads = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Treat as image upload by default
        $this->image();
        $this->multiple();
        $this->previewable(false);

        // Intercept the save process to send the file to the lume API and
        // store the returned filename in the field state / database.
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
            $endpoint = $this->endpoint ?? config('mediatonic.endpoint');
            $responseKey = $this->responseKey ?? config('mediatonic.response_key', 'filename');

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set mediatonic.endpoint in your config.');
            }

            $api = new Api();
            $request = new CreateAsset(
                siteId: null,
                file: $file,
            );
            $response = $api->send($request);

            // Parse JSON response when possible
            $json = $response->json()['data'];

            // Determine the filename to return
            $filename = null;
            if (array_key_exists($responseKey, $json)) {
                $filename = (string)$json[$responseKey];
            }

            // Get the file details from the temporary uploaded file
            $width = $height = null;
            try {
                $imageInfo = @getimagesize($file->getRealPath());
                if ($imageInfo) {
                    $width = $imageInfo[0] ?? null;
                    $height = $imageInfo[1] ?? null;
                }
            } catch (\Throwable) {
                // ignore
            }

            $fileConfig = [
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'hash_name' => method_exists($file, 'hashName') ? $file->hashName() : null,
            ];

            // Optionally record the upload in the igs_media table
            $shouldRecord = $this->recordUploads ?? (bool) config('mediatonic.record_uploads', true);
            if ($shouldRecord) {
                $this->recordUpload($filename, $fileConfig, $json['uuid'], is_array($json) ? $json : null);
            }

            return $filename;
        });
    }

    /**
     * Override API endpoint per field instance.
     */
    public function endpoint(?string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Override the multipart file field name per field instance.
     */
    public function fileField(string $name): static
    {
        $this->fileField = $name;

        return $this;
    }

    /**
     * Override the response key used to extract the filename per field instance.
     */
    public function responseKey(?string $key): static
    {
        $this->responseKey = $key;

        return $this;
    }

    /**
     * Enable/disable recording uploads to the igs_media table for this field instance.
     */
    public function recordToMedia(bool $enabled = true): static
    {
        $this->recordUploads = $enabled;

        return $this;
    }

    /**
     * Insert a row into the igs_media table if model context is available.
     */
    protected function recordUpload(string $filename, array $fileConfig, string $uuid, ?array $jsonResponse = null): void
    {
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

        DB::table(get_mediatonic_table_name())->insert([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'uuid' => $uuid,
            'filename' => $filename,
            'presets' => $presets !== null ? json_encode($presets) : null,
            'config' => json_encode($fileConfig),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
