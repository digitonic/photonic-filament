<?php

namespace Digitonic\Photonic\Filament\Forms\Components;

use Digitonic\Photonic\Filament\Services\MediaUploadService;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PhotonicInput extends FileUpload
{
    /**
     * Whether to return the media ID instead of filename.
     * When true, the field will be hydrated with the media ID for storage.
     */
    protected bool $returnMediaId = false;

    /**
     * Track processed files to prevent duplicate uploads during multiple dehydration cycles.
     * Maps file identifier => result (media ID or filename)
     *
     * @var array<string, string|int>
     */
    protected array $processedFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Configure as image upload with private visibility
        $this->image();
        $this->visibility('private');
        $this->downloadable(false);
        $this->openable(false);

        // Process files when form is saved
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $livewire, $get): string|int {
            // Generate unique identifier for this file to prevent duplicate processing
            // Use filename + path as identifier (survives across dehydration cycles)
            $fileIdentifier = $file->getFilename().'|'.$file->path();

            // Check if we've already processed this file
            if (array_key_exists($fileIdentifier, $this->processedFiles)) {
                dump('returning same file found', $fileIdentifier, $this->processedFiles);

                // Return the cached result from the first processing
                return $this->processedFiles[$fileIdentifier];
            }

            // Cache the result to prevent reprocessing on subsequent dehydration cycles
            // This will return failed if the process fails after this, but wont process any additional calls.
            $this->processedFiles[$fileIdentifier] = 'failed';

            $endpoint = config('photonic-filament.endpoint');

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set photonic-filament.endpoint in your config.');
            }

            $modelInstance = null;

            // Use service to persist media if we have model context
            if ($this->returnMediaId === false) {
                $modelClass = $this->getModel();
                $modelId = $this->resolveCurrentRecordId();
                if ($modelId) {
                    $modelInstance = $modelClass::find($modelId);
                }
            }

            $service = new MediaUploadService;

            // When returning media ID (ID mode), don't associate with model via morph columns
            // This allows the same model to have both ID-based and relationship-based media
            $media = $service->upload($file, [
                'model' => $this->returnMediaId ? null : $modelInstance,
                'alt' => '',
                'title' => '',
                'description' => '',
                'caption' => '',
            ]);

            if (! $modelInstance && ! $this->returnMediaId) {
                $livewire->dispatch('pending-media-created', mediaId: $media->id);
            }

            // Return media ID or filename based on configuration
            $result = $this->returnMediaId && $media->id !== null
                ? (string) $media->id
                : $media->asset_uuid;

            // Cache the result to prevent reprocessing on subsequent dehydration cycles
            $this->processedFiles[$fileIdentifier] = $result;

            return $result;
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
     * Best-effort resolution of the current record ID from the Livewire component.
     */
    protected function resolveCurrentRecordId(): ?int
    {
        $livewire = $this->getLivewire();

        // Try method-based API
        if (method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();
            if ($record && method_exists($record, 'getKey')) {
                return (int) $record->getKey();
            }
        }

        return null;
    }
}
