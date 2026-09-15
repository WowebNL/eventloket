<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Livewire\Zaken\ListDocumentAuditTrails;

/**
 * Maps a raw ZGW audit-trail `actieWeergave` to a friendly, localized label.
 *
 * Re-homed from the old DocumentAuditTrail value object so the localization
 * lives next to the other ZGW services. The kernel audit-trail endpoint returns
 * plain arrays. A typed AuditTrailData exists in the package (via Typed::wrap()),
 * but the rows are deliberately kept as arrays so they survive Livewire's public
 * property serialization in {@see ListDocumentAuditTrails};
 * callers enrich those raw rows with this label.
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
