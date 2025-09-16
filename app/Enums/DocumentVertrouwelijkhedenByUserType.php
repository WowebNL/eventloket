<?php

namespace App\Enums;

use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\OrganiserUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;

enum DocumentVertrouwelijkhedenByUserType: string
{
    case Openbaar = 'openbaar';
    case BeperktOpenbaar = 'beperkt_openbaar';
    case Intern = 'intern';
    case Zaakvertrouwelijk = 'zaakvertrouwelijk';
    case Vertrouwelijk = 'vertrouwelijk';
    case Confidentieel = 'confidentieel';
    case Geheim = 'geheim';
    case ZeerGegeheim = 'zeer_geheim';

    public static function fromUserType(string $userType): array
    {
        return match ($userType) {
            OrganiserUser::class => [self::Zaakvertrouwelijk->value],
            AdvisorUser::class => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value],
            MunicipalityAdminUser::class => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            ReviewerMunicipalityAdminUser::class => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            ReviewerUser::class => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            AdminUser::class => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            default => [self::Openbaar->value],
        };
    }
}
