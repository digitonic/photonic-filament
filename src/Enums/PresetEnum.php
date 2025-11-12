<?php

namespace Digitonic\MediaTonic\Filament\Enums;

use Filament\Support\Contracts\HasLabel;

enum PresetEnum: string implements HasLabel
{
    case ORIGINAL = 'original';

    public function getLabel(): string
    {
        return 'Original';
    }
}
