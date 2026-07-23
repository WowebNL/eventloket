<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

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
enum ZaaktypeRole: string implements HasLabel
{
    case Vergunning = 'vergunning';
    case Melding = 'melding';
    case Vooraankondiging = 'vooraankondiging';
    case Doorkomst = 'doorkomst';

    public function getLabel(): string
    {
        return __("enums/zaaktype_role.{$this->value}");
    }

    /**
     * Derive a role from a zaaktype name by matching its {@see namePrefix()},
     * the convention the shared catalogus uses. Returns null when nothing matches.
     */
    public static function fromName(?string $name): ?self
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        $lower = mb_strtolower($name);

        foreach (self::cases() as $role) {
            if (str_starts_with($lower, mb_strtolower($role->namePrefix()))) {
                return $role;
            }
        }

        return null;
    }

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
