<?php

namespace Digitonic\Mediatonic\Filament\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PresetEnum: string implements HasLabel
{
    case ORIGINAL = 'original';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ORIGINAL => 'Original',
        };
    }
}
