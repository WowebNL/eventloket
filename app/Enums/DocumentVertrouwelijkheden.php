<?php

namespace App\Enums;

use App\Services\Zgw\DocumentAudience;
use App\Services\Zgw\ZgwConnectionConfig;

/**
 * The eight vertrouwelijkheidaanduiding values of the ZGW standard.
 *
 * The cases are declared in the standard's own order, from the least to the most
 * confidential level. That order is meaningful: the standard treats a maximum
 * vertrouwelijkheidaanduiding as inclusive, so being allowed to see one level
 * implies being allowed to see every less confidential one. {@see order()},
 * {@see atMost()} and {@see mostConfidential()} are the only places that should
 * express it, so the ordering lives in exactly one spot.
 *
 * Without a per-connection map the application uses only Zaakvertrouwelijk,
 * Vertrouwelijk and Confidentieel; a connection with its own map may use the
 * full scale.
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
     * The vertrouwelijkheid levels a role may see when the connection carries no
     * map of its own, ordered from least to most confidential.
     *
     * These are the legacy defaults and they are deliberately not derived from a
     * maximum: they skip openbaar, beperkt_openbaar and intern entirely. A
     * connection that wants the inclusive behaviour of the standard configures a
     * maximum per role group instead (see {@see ZgwConnectionConfig::documentVisibilityForRole()}).
     *
     * @return array<int, string>
     */
    public static function fromUserRole(Role $role): array
    {
        return match ($role) {
            Role::Organiser => [self::Zaakvertrouwelijk->value],
            Role::Advisor => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value],
            Role::MunicipalityAdmin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::ReviewerMunicipalityAdmin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Coordinator => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Reviewer => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::Admin => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
            Role::KoppelingBeheerder => [self::Zaakvertrouwelijk->value, self::Vertrouwelijk->value, self::Confidentieel->value],
        };
    }

    /**
     * All levels in the standard's order, from the least to the most confidential.
     *
     * @return array<int, string>
     */
    public static function order(): array
    {
        return array_map(static fn (self $level): string => $level->value, self::cases());
    }

    /**
     * The position of a level in that order, or null for a value outside the
     * standard.
     */
    public static function rank(string $level): ?int
    {
        $position = array_search($level, self::order(), true);

        return $position === false ? null : $position;
    }

    /**
     * Every level up to and including the given maximum, ordered from the least
     * to the most confidential.
     *
     * This is what a maximum vertrouwelijkheidaanduiding means in the standard:
     * it is inclusive over the ordered scale, so a maximum of `intern` also
     * covers `beperkt_openbaar` and `openbaar`. An unknown maximum yields an
     * empty list rather than a guess.
     *
     * @return array<int, string>
     */
    public static function atMost(string $max): array
    {
        $rank = self::rank($max);

        if ($rank === null) {
            return [];
        }

        return array_slice(self::order(), 0, $rank + 1);
    }

    /**
     * The most confidential of the given levels, or null when none of them is a
     * level of the standard. Used to read a maximum out of a stored set.
     *
     * @param  array<int|string, mixed>  $levels
     */
    public static function mostConfidential(array $levels): ?string
    {
        $highest = null;
        $highestRank = -1;

        foreach ($levels as $level) {
            if (! is_string($level)) {
                continue;
            }

            $rank = self::rank($level);

            if ($rank !== null && $rank > $highestRank) {
                $highest = $level;
                $highestRank = $rank;
            }
        }

        return $highest;
    }

    /**
     * The fixed upload choices for the default connection, ordered from least to
     * most confidential. Used only for the default connection and for any
     * connection without its own vertrouwelijkheid map; a connection with a map
     * derives its levels from the maximum configured per role group instead
     * (see {@see DocumentAudience}).
     *
     * The application deliberately uses only these three of the eight ZGW levels.
     * Which of them are actually offered, and which roles each of them reaches,
     * still follows the connection's map, so this list must never be used to
     * label the choice: it says nothing about who sees what.
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
