<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use Woweb\Zgw\Data\Generated\Catalogi\StatusTypeData;

/**
 * Classifies a zaak's current statustype into a coarse phase.
 *
 * Re-homed from the old `OzStatustype` value object so the app-specific
 * interpretation lives next to the other ZGW services rather than on a data
 * carrier. All methods are null-tolerant: a zaak without a (matching)
 * statustype is treated as "not in that phase".
 */
final class StatusPhase
{
    public static function isReceived(?StatusTypeData $statustype): bool
    {
        return $statustype?->volgnummer === 1;
    }

    public static function isFinalised(?StatusTypeData $statustype): bool
    {
        return $statustype?->isEindstatus === true;
    }

    public static function isInProgress(?StatusTypeData $statustype): bool
    {
        return $statustype !== null
            && $statustype->volgnummer !== null
            && $statustype->volgnummer > 1
            && ! self::isFinalised($statustype);
    }
}
