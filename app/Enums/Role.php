<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin'; // Functioneel beheerder
    case MunicipalityAdmin = 'municipality_admin'; // Functioneel beheerder per gemeente
    case Reviewer = 'reviewer'; // Behandelaar
    case Advisor = 'advisor'; // Adviesdienst medewerker
    case Organiser = 'organiser'; // Organisator

    public function getLabel(): string
    {
        return __("enums/role.{$this->value}.label");
    }
}
