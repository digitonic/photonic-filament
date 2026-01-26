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

    protected function setUp(): void
    {
        parent::setUp();

        // Configure as image upload with private visibility
        $this->image();
        $this->multiple();
        $this->visibility('private');
        $this->downloadable(false);
        $this->openable(false);
        $this->live();

        // Process files when form is saved
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string|int {
            $endpoint = config('photonic-filament.endpoint');

            if (blank($endpoint)) {
                throw new \RuntimeException('Endpoint is not configured. Set photonic-filament.endpoint in your config.');
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

            // Return media ID or filename based on configuration
            if ($this->returnMediaId && $media->id !== null) {
                return (string) $media->id;
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

        // Try method-based API
        if (method_exists($livewire, 'getRecord')) {
            $record = $livewire->getRecord();
            if ($record && method_exists($record, 'getKey')) {
                return (int) $record->getKey();
            }
        }

        // Try common public properties
        foreach (['record', 'ownerRecord'] as $prop) {
            if (property_exists($livewire, $prop)) {
                $record = $livewire->{$prop};
                if ($record && method_exists($record, 'getKey')) {
                    return (int) $record->getKey();
                }
            }
        }

        // Try form state
        if (! empty($livewire->form) && method_exists($livewire->form, 'getState')) {
            try {
                $state = $livewire->form->getState();
                foreach (['id', $this->getStatePath().'.id'] as $key) {
                    if (is_array($state) && array_key_exists($key, $state) && $state[$key]) {
                        return (int) $state[$key];
                    }
                }
            } catch (\Throwable) {
                // Silently fail if state cannot be retrieved
            }
        }

        return null;
    }
}
