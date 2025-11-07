<?php

namespace Digitonic\Mediatonic\Filament\Forms\Components;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API; // ensure correct class import
use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests\CreateAsset;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediatonicInput extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        // Treat as image upload by default
        $this->image();
        $this->multiple();
        $this->previewable(false);

        // Intercept the save process to send the file to the Mediatonic API and
        // store the returned filename in the field state / database.
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
            $endpoint = config('mediatonic.endpoint');
            $responseKey = config('mediatonic.response_key', 'filename');
            $shouldRecord = (bool) config('mediatonic.record_uploads', true);

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set mediatonic.endpoint in your config.');
            }

            // Correctly instantiate API connector
            $api = new API;
            $request = new CreateAsset(
                siteId: null,
                file: $file,
            );
            $response = $api->send($request);

            // Parse JSON response when possible
            $json = $response->json()['data'] ?? [];

            // Determine the filename to return
            $filename = null;
            if (array_key_exists($responseKey, $json)) {
                $filename = (string) $json[$responseKey];
            }

            // Get the file details from the temporary uploaded file
            $width = $height = null;
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo !== false) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }

            $fileConfig = [
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
                'hash_name' => $file->hashName(),
            ];

            if ($shouldRecord) {
                $this->recordUpload(
                    filename: $filename ?? '',
                    fileConfig: $fileConfig,
                    jsonResponse: $json,
                );
            }

            return $filename ?? '';
        });
    }

    /**
     * @param  array<string, int|string|null>  $fileConfig
     * @param  array<string, mixed>|null  $jsonResponse
     */
    protected function recordUpload(string $filename, array $fileConfig, ?array $jsonResponse = null): void
    {
        $modelClass = $this->getModel();
        $modelId = $this->resolveCurrentRecordId();
        $mediaModelClass = config('mediatonic.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);

        if (! $modelClass || ! $modelId) {
            // No model context; skip recording.
            return;
        }

        // Store asset_uuid (matches migration) if present in response
        $assetUuid = $jsonResponse['uuid'] ?? null;

        $mediaModelClass::create([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'asset_uuid' => $assetUuid,
            'filename' => $filename,
            'config' => json_encode($fileConfig),
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
        if (! empty($livewire->form) && method_exists($livewire->form, 'getState')) {
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
