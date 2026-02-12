<?php

namespace Digitonic\Photonic\Filament\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Digitonic\Photonic\Filament\Support\PhotonicResolver for(\Digitonic\Photonic\Filament\Models\Media|int $media)
 */
class Photonic extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'photonic';
    }
}
