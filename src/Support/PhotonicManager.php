<?php

namespace Digitonic\Photonic\Filament\Support;

use Digitonic\Photonic\Filament\Models\Media;

class PhotonicManager
{
    public function make(): PhotonicResolver
    {
        return PhotonicResolver::make();
    }

    public function for(Media|int $media): PhotonicResolver
    {
        return $this->make()->for($media);
    }
}
