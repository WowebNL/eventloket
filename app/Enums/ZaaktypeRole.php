<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The logical Eventloket role a zaaktype fulfils for a municipality.
 *
 * Replaces the loose string constants that used to live on
 * `DetermineAanvraagType` and the name-prefix `match` in `ResolveZaaktype`.
 * The backing values are kept identical to those old constants so existing
 * snapshots and comparisons keep working.
 *
 * `Doorkomst` is not produced by `DetermineAanvraagType` (it is derived from
 * `Zaaktype::$triggers_route_check` in `CreateDoorkomstZaken`); it exists here
 * so the per-municipality blueprint can map it too.
 */
enum ZaaktypeRole: string
{
    case Vergunning = 'vergunning';
    case Melding = 'melding';
    case Vooraankondiging = 'vooraankondiging';
    case Doorkomst = 'doorkomst';

    /**
     * The zaaktype-name prefix used as the fallback heuristic when no
     * blueprint mapping is configured. Matches the conventions that
     * `SyncZaaktypen` relies on.
     */
    public function namePrefix(): string
    {
        return match ($this) {
            self::Vergunning => 'Evenementenvergunning',
            self::Melding => 'Melding',
            self::Vooraankondiging => 'Vooraankondiging',
            self::Doorkomst => 'Doorkomst',
        };
    }
}
