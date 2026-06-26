<?php

declare(strict_types=1);

namespace App\Services\Zgw;

/**
 * Maps a raw ZGW audit-trail `actieWeergave` to a friendly, localized label.
 *
 * Re-homed from the old DocumentAuditTrail value object so the localization
 * lives next to the other ZGW services. The audit-trail endpoint has no typed
 * DTO in the package (it returns plain arrays), so callers keep the raw rows
 * and enrich them with this label.
 */
final class AuditTrailActionFormatter
{
    public static function friendly(string $actieWeergave): string
    {
        return match ($actieWeergave) {
            'Object aangemaakt' => __('Document aangemaakt'),
            'Object deels bijgewerkt' => __('Document gewijzigd'),
            default => $actieWeergave,
        };
    }
}
