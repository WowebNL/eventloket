<?php

namespace App\Enums;

use App\Services\Zgw\DocumentAudience;

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

    /**
     * The vertrouwelijkheid levels a role may see by default, ordered from least
     * to most confidential.
     *
     * `openbaar` is included for every role: it is the least confidential level
     * there is, so a role that may see zaakvertrouwelijk documents can certainly
     * see public ones. Leaving it out meant a backend that labels documents
     * `openbaar` (OneGround/RX Mission does this for system uploads) showed no
     * documents at all, to nobody, while more confidential documents were
     * visible.
     */
    public static function fromUserRole(Role $role): array
    {
        return match ($role) {
            Role::Organiser => [self::Openbaar->value, self::Zaakvertrouwelijk->value],
            Role::Advisor => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value],
            Role::MunicipalityAdmin => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::ReviewerMunicipalityAdmin => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Coordinator => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Reviewer => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Admin => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::KoppelingBeheerder => [self::Openbaar->value, self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
        };
    }

    /**
     * The levels a municipal user is offered when uploading a document, ordered
     * from least to most confidential. The application deliberately uses only
     * these three of the eight ZGW levels.
     *
     * Which of them are actually offered, and which roles each of them reaches,
     * follows the vertrouwelijkheid map of the connection the zaak runs on; see
     * {@see DocumentAudience}. This list must therefore never be used to label
     * the choice: it says nothing about who sees what.
     *
     * @return array<int, string>
     */
    public static function uploadChoices(): array
    {
        return [
            self::Zaakvertrouwelijk->value,
            self::Vertrouwelijk->value,
            self::Confidentieel->value,
        ];
    }
}
