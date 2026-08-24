<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Presentation of the risk classification of a case.
 *
 * The classification is stored as a plain string, both in the case reference
 * data and as a ZGW zaakeigenschap. Next to the calculated classifications
 * A, B and C there is a manual one that is stored as "0". Its display value
 * is "M"; the stored value stays "0" so existing cases, filters and ZGW
 * properties keep working.
 */
class RisicoClassificatie
{
    public const MANUAL = '0';

    public const MANUAL_LABEL = 'M';

    /**
     * The selectable classifications, keyed by stored value. PHP casts the
     * numeric key "0" to an integer; a lookup with the string "0" resolves to
     * the same entry.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        return [
            self::MANUAL => self::MANUAL_LABEL,
            'A' => 'A',
            'B' => 'B',
            'C' => 'C',
        ];
    }

    /**
     * The display value for a stored classification. Unknown values are
     * returned unchanged so nothing disappears from the screen.
     */
    public static function label(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return self::options()[$value] ?? $value;
    }
}
