<?php

namespace Digitonic\MediaTonic\Filament\Forms\Components;

use Digitonic\MediaTonic\Filament\Http\Integrations\MediaTonic\API;
use Digitonic\MediaTonic\Filament\Services\MediaUploadService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaTonicInput extends FileUpload
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

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set mediatonic-filament.endpoint in your config.');
            }

            $modelInstance = null;
            // Use service to persist media if we have model context
            if ($this->returnMediaId === false) {
                $modelClass = $this->getModel();
                $modelId = $this->resolveCurrentRecordId();
                $modelInstance = $modelClass::find($modelId);
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

            $mediaId = $media->id;

            if ($this->returnMediaId && $mediaId !== null) {
                return (string) $mediaId;
            }

            return $media->filename;
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
