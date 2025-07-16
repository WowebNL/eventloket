<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin'; // Functioneel beheerder
    case MunicipalityAdmin = 'municipality_admin'; // Functioneel beheerder per gemeente
    case Reviewer = 'reviewer'; // Behandelaar
    case Advisor = 'advisor'; // Adviesdienst medewerker
    case Organiser = 'organiser'; // Organisator
}
