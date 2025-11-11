<?php

namespace Digitonic\Mediatonic\Filament\Forms\Components;

use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\API; // ensure correct class import
use Digitonic\Mediatonic\Filament\Http\Integrations\Mediatonic\Requests\CreateAsset;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediatonicInput extends FileUpload
{
    /**
     * Whether to return the media ID instead of filename.
     * When true, the field will be hydrated with the media ID for storage.
     */
    protected bool $returnMediaId = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Treat as image upload by default
        $this->image();
        $this->multiple();
        $this->previewable(false);

        // Intercept the save process to send the file to the Mediatonic API and
        // store the returned filename in the field state / database.
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string|int {
            $endpoint = config('mediatonic-filament.endpoint');
            $responseKey = config('mediatonic-filament.response_key', 'filename');
            $shouldRecord = (bool) config('mediatonic-filament.record_uploads', true);

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set mediatonic-filament.endpoint in your config.');
            }

            // Handle both local and S3 storage scenarios
            $filePath = $file->getRealPath();
            if (! file_exists($filePath)) {
                // Livewire is using S3 or cloud storage, read from temporary URL
                $fileStream = fopen($file->temporaryUrl(), 'r');
            } else {
                // Local storage
                $fileStream = fopen($filePath, 'r');
            }

            // Correctly instantiate API connector
            $api = new API;
            
            // Get metadata from the form state if available
            $livewire = $this->getLivewire();
            $state = method_exists($livewire, 'getState') ? $livewire->getState() : [];
            
            $alt = $state['mediatonic_alt'] ?? null;
            $title = $state['mediatonic_title'] ?? null;
            $description = $state['mediatonic_description'] ?? null;
            $caption = $state['mediatonic_caption'] ?? null;
            
            $request = new CreateAsset(
                siteId: null,
                fileStream: $fileStream,
                fileName: $file->getClientOriginalName(),
                alt: $alt,
                title: $title,
                description: $description,
                caption: $caption
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

            $mediaId = null;
            if ($shouldRecord) {
                $mediaId = $this->recordUpload(
                    filename: $filename ?? '',
                    fileConfig: $fileConfig,
                    jsonResponse: $json,
                    alt: $alt,
                    title: $title,
                    description: $description,
                    caption: $caption,
                );
            }

            // Return media ID if configured, otherwise return filename
            if ($this->returnMediaId && $mediaId !== null) {
                return (string) $mediaId;
            }

            return $filename ?? '';
        });
    }

    /**
     * Configure the component to return the media ID instead of filename.
     * Useful for storing a direct reference to the media record.
     */
    public function returnId(bool $return = true): static
    {
        $this->returnMediaId = $return;

        return $this;
    }

    /**
     * @param  array<string, int|string|null>  $fileConfig
     * @param  array<string, mixed>|null  $jsonResponse
     * @return int|null The ID of the created media record, or null if not created
     */
    protected function recordUpload(
        string $filename,
        array $fileConfig,
        ?array $jsonResponse = null,
        ?string $alt = null,
        ?string $title = null,
        ?string $description = null,
        ?string $caption = null
    ): ?int {
        $mediaModelClass = config('mediatonic-filament.media_model', \Digitonic\Mediatonic\Filament\Models\Media::class);
        
        // Store asset_uuid (matches migration) if present in response
        $assetUuid = $jsonResponse['uuid'] ?? null;

        // When returnMediaId is true, we create a standalone media record without polymorphic relation
        if ($this->returnMediaId) {
            $media = $mediaModelClass::create([
                'model_type' => null,
                'model_id' => null,
                'asset_uuid' => $assetUuid,
                'filename' => $filename,
                'alt' => $alt,
                'title' => $title,
                'description' => $description,
                'caption' => $caption,
                'config' => $fileConfig,
            ]);

            return $media->id;
        }

        // Original behavior: create with polymorphic relation
        $modelClass = $this->getModel();
        $modelId = $this->resolveCurrentRecordId();

        if (! $modelClass || ! $modelId) {
            // No model context; skip recording.
            return null;
        }

        $media = $mediaModelClass::create([
            'model_type' => $modelClass,
            'model_id' => $modelId,
            'asset_uuid' => $assetUuid,
            'filename' => $filename,
            'alt' => $alt,
            'title' => $title,
            'description' => $description,
            'caption' => $caption,
            'config' => $fileConfig,
        ]);

        return $media->id;
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
