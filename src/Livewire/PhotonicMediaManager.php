<?php

namespace Digitonic\Photonic\Filament\Livewire;

use Digitonic\Photonic\Filament\Enums\PresetEnum;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\API;
use Digitonic\Photonic\Filament\Http\Integrations\Photonic\Requests\DeleteAsset;
use Digitonic\Photonic\Filament\Models\Media;
use Livewire\Component;

class PhotonicMediaManager extends Component
{
    public $mediaId = null;

    public ?Media $media = null;

    public string $preset = PresetEnum::ORIGINAL->value;

    public string $previewClasses = 'rounded-xl max-w-full';

    public string $fieldName;

    public function mount($mediaId = null, ?string $preset = null, ?string $previewClasses = null, string $fieldName = 'media_id')
    {
        // Handle different types that might be passed as mediaId
        if ($mediaId instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            // If it's a file upload, ignore it - we're waiting for the actual media ID
            $this->mediaId = null;
        } elseif (is_numeric($mediaId)) {
            $this->mediaId = (int) $mediaId;
        } else {
            $this->mediaId = null;
        }
        
        $this->preset = $preset ?? PresetEnum::ORIGINAL->value;
        $this->previewClasses = $previewClasses ?? 'rounded-xl max-w-full';
        $this->fieldName = $fieldName;

        $this->loadMedia();
    }

    public function loadMedia()
    {
        if ($this->mediaId && is_numeric($this->mediaId)) {
            $mediaModelClass = config('photonic-filament.media_model', Media::class);
            $this->media = $mediaModelClass::find((int) $this->mediaId);
        } else {
            $this->media = null;
        }
    }

    public function updatedMediaId($value)
    {
        // Handle different types that might be assigned
        if ($value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            // If it's a file upload, ignore it - we're waiting for the actual media ID
            return;
        }
        
        if (is_numeric($value)) {
            $this->mediaId = (int) $value;
        } else {
            $this->mediaId = null;
        }
        
        $this->loadMedia();
    }

    public function render()
    {
        return view('photonic-filament::livewire.photonic-media-manager');
    }
}
