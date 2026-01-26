<?php

namespace Digitonic\Photonic\Filament\Forms\Components;

use Digitonic\Photonic\Filament\Services\MediaUploadService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Log;
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

        // Treat as image upload by default
        $this->image();
        $this->multiple();
        
        // Set visibility to private to prevent Filament from trying to generate preview URLs
        $this->visibility('private');
        $this->downloadable(false);
        $this->openable(false);
        
        // Enable live updates so afterStateUpdated is triggered immediately
        $this->live();
        
        // IMPORTANT: Process files immediately after upload, not on form save
        // NOTE: This can be overridden by parent components, so we'll also keep saveUploadedFileUsing
        $this->afterStateUpdated(function ($state, $set, $livewire) {
            Log::info('PhotonicInput: afterStateUpdated triggered', [
                'state_type' => is_array($state) ? 'array' : gettype($state),
                'state_count' => is_array($state) ? count($state) : 'n/a',
                'state_value' => is_array($state) ? array_map(fn($item) => is_object($item) ? get_class($item) : $item, $state) : $state,
            ]);
            
            // $state will be an array of TemporaryUploadedFile objects after file selection
            if (! $state) {
                Log::info('PhotonicInput: State is empty, skipping');
                return;
            }
            
            // Handle array of files (even if single file, Livewire wraps it in array)
            $files = is_array($state) ? $state : [$state];
            $processedValues = [];
            
            foreach ($files as $file) {
                Log::info('PhotonicInput: Processing file', [
                    'file_type' => get_debug_type($file),
                    'is_temp_file' => $file instanceof TemporaryUploadedFile,
                ]);
                
                if ($file instanceof TemporaryUploadedFile) {
                    Log::info('PhotonicInput: Processing uploaded file immediately', [
                        'filename' => $file->getClientOriginalName(),
                        'returnMediaId' => $this->returnMediaId,
                    ]);
                    
                    try {
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
                        $media = $service->upload($file, [
                            'model' => $this->returnMediaId ? null : $modelInstance,
                            'alt' => '',
                            'title' => '',
                            'description' => '',
                            'caption' => '',
                        ]);

                        $mediaId = $media->id;
                        
                        Log::info('PhotonicInput: Upload complete', [
                            'media_id' => $mediaId,
                            'filename' => $media->filename,
                        ]);

                        // Store the result based on mode
                        if ($this->returnMediaId && $mediaId !== null) {
                            $processedValues[] = (string) $mediaId;
                        } else {
                            $processedValues[] = $media->filename;
                        }
                    } catch (\Exception $e) {
                        Log::error('PhotonicInput: Upload failed', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e;
                    }
                } else {
                    // Already processed value (string ID or filename)
                    $processedValues[] = $file;
                }
            }
            
            // Update the field state with processed values
            $result = $this->isMultiple() ? $processedValues : ($processedValues[0] ?? null);
            $set($this->getStatePath(), $result);
        });

        // Keep saveUploadedFileUsing for compatibility, but it won't be called now
        $this->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string|int {
            Log::info('PhotonicInput: saveUploadedFileUsing called', [
                'filename' => $file->getClientOriginalName(),
                'returnMediaId' => $this->returnMediaId,
            ]);
            
            $endpoint = config('photonic-filament.endpoint');
            $responseKey = config('photonic-filament.response_key', 'filename');

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

            $mediaId = $media->id;
            
            Log::info('PhotonicInput: Upload complete', [
                'media_id' => $mediaId,
                'filename' => $media->filename,
                'returnMediaId' => $this->returnMediaId,
                'will_return' => $this->returnMediaId && $mediaId !== null ? (string) $mediaId : $media->filename,
            ]);

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
