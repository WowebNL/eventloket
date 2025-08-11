<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganisationRole: string implements HasLabel
{
    case Member = 'member';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return __("enums/organisation-role.{$this->value}.label");
    }

    public static function getOptions(): array
    {
        static $options = null;

        if ($options === null) {
            $options = [];
            foreach (self::cases() as $case) {
                $options[$case->value] = $case->getLabel();
            }
        }

        return $options;
    }
}
