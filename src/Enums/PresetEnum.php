<?php

namespace Digitonic\Photonic\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

enum PresetEnum: string implements HasLabel
{
    case ORIGINAL = 'original';
    case AUTO = 'auto';

    public function getLabel(): string
    {
        return match ($this) {
            self::ORIGINAL => 'Original',
            self::AUTO => 'Auto',
        };
    }
}
