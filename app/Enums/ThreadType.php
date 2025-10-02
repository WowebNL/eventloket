<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ThreadType: string implements HasLabel
{
    case Advice = 'advice';
    case Organiser = 'organiser';

    public function getLabel(): string
    {
        return __("enums/thread-type.{$this->value}.label");
    }
}
