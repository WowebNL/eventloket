<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Role: string implements HasLabel
{
    case Admin = 'admin'; // Platformbeheerder
    case MunicipalityAdmin = 'municipality_admin'; // Gemeentelijk beheerder
    case ReviewerMunicipalityAdmin = 'reviewer_municipality_admin'; // Gemeentelijk beheerder (+behandelaar)
    case Coordinator = 'coordinator'; // Coördinator (+behandelaar)
    case Reviewer = 'reviewer'; // Behandelaar
    case Advisor = 'advisor'; // Adviesdienst medewerker
    case Organiser = 'organiser'; // Organisator
    case ArchiveCoordinator = 'archive_coordinator'; // Archief coördinator
    case ArchiveReviewer = 'archive_reviewer'; // Archief beoordelaar

    public function getLabel(): string
    {
        return __("enums/role.{$this->value}.label");
    }
}
