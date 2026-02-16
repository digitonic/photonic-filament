<?php

namespace Digitonic\Photonic\Filament\Support;

use Digitonic\Photonic\Filament\Models\Media;

class PhotonicManager
{
    public function for(Media|int $media): PhotonicResolver
    {
        return (new PhotonicResolver)->for($media);
    }
}
