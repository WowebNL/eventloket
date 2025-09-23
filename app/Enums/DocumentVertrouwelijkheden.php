<?php

namespace App\Enums;

/**
 * We only use Zaakvertrouwelijk, Vertrouwelijk and Confidentieel in the app.
 */
enum DocumentVertrouwelijkheden: string
{
    case Openbaar = 'openbaar';
    case BeperktOpenbaar = 'beperkt_openbaar';
    case Intern = 'intern';
    case Zaakvertrouwelijk = 'zaakvertrouwelijk';
    case Vertrouwelijk = 'vertrouwelijk';
    case Confidentieel = 'confidentieel';
    case Geheim = 'geheim';
    case ZeerGegeheim = 'zeer_geheim';

    public static function fromUserRole(Role $role): array
    {
        return match ($role) {
            Role::Organiser => [self::Zaakvertrouwelijk->value],
            Role::Advisor => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value],
            Role::MunicipalityAdmin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::ReviewerMunicipalityAdmin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Reviewer => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Admin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value]
        };
    }

    public static function listUserRoles(): array
    {
        return [
            self::Zaakvertrouwelijk->value => [
                // Role::Admin,
                // Role::MunicipalityAdmin,
                // Role::ReviewerMunicipalityAdmin,
                Role::Reviewer,
                Role::Advisor,
                Role::Organiser,
            ],
            self::Vertrouwelijk->value => [
                // Role::Admin,
                // Role::MunicipalityAdmin,
                // Role::ReviewerMunicipalityAdmin,
                Role::Reviewer,
                Role::Advisor,
            ],
            self::Confidentieel->value => [
                // Role::Admin,
                // Role::MunicipalityAdmin,
                // Role::ReviewerMunicipalityAdmin,
                Role::Reviewer,
            ],
        ];
    }
}
