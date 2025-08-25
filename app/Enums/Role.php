<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin'; // Platformbeheerder
    case MunicipalityAdmin = 'municipality_admin'; // Gemeentelijk beheerder
    case ReviewerMunicipalityAdmin = 'reviewer_municipality_admin'; // Behandelaar en gemeentelijk beheerder
    case Reviewer = 'reviewer'; // Behandelaar
    case Advisor = 'advisor'; // Adviesdienst medewerker
    case Organiser = 'organiser'; // Organisator

    public function getLabel(): string
    {
        return __("enums/role.{$this->value}.label");
    }
}
