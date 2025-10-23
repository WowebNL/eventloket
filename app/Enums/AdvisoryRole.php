<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AdvisoryRole: string implements HasLabel
{
    case Member = 'member';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return __("enums/advisory-role.{$this->value}.label");
    }
}
