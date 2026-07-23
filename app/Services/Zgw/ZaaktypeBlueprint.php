<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Models\MunicipalityZaaktypeMapping;
use Illuminate\Support\Collection;

/**
 * Resolves the concrete ZGW resource for each blueprint slot.
 *
 * Each method takes the (possibly null) {@see MunicipalityZaaktypeMapping} for
 * the zaaktype plus the live catalogus candidates. When the mapping names a
 * selector and it matches a candidate, that candidate wins; otherwise the
 * original name/volgnummer/omschrijvingGeneriek heuristic is used. So an empty
 * blueprint reproduces the pre-blueprint behaviour exactly.
 */
final class ZaaktypeBlueprint
{
    /**
     * The ZGW eigenschap naam for a logical FormState key. Defaults to the
     * logical key itself (our own OpenZaak names them identically).
     */
    public static function eigenschapNaam(?MunicipalityZaaktypeMapping $mapping, string $logicalKey): string
    {
        $naam = $mapping?->eigenschap_map[$logicalKey] ?? null;

        return is_string($naam) && $naam !== '' ? $naam : $logicalKey;
    }

    /**
     * The initial statustype. Heuristic: the lowest volgnummer.
     *
     * @param  iterable<array<string, mixed>>  $statustypen
     * @return array<string, mixed>|null
     */
    public static function initialStatustype(?MunicipalityZaaktypeMapping $mapping, iterable $statustypen): ?array
    {
        $statustypen = collect($statustypen);

        if ($mapping?->initial_statustype) {
            $match = $statustypen->firstWhere('omschrijving', $mapping->initial_statustype);
            if ($match) {
                return $match;
            }
        }

        return $statustypen->sortBy('volgnummer')->first();
    }

    /**
     * The final statustype. Heuristic: the one flagged isEindstatus.
     *
     * @param  iterable<array<string, mixed>>  $statustypen
     * @return array<string, mixed>|null
     */
    public static function eindStatustype(?MunicipalityZaaktypeMapping $mapping, iterable $statustypen): ?array
    {
        $statustypen = collect($statustypen);

        if ($mapping?->eind_statustype) {
            $match = $statustypen->firstWhere('omschrijving', $mapping->eind_statustype);
            if ($match) {
                return $match;
            }
        }

        return $statustypen->firstWhere('isEindstatus', true);
    }

    /**
     * The initiator roltype. Heuristic: omschrijvingGeneriek === 'initiator'.
     *
     * @param  iterable<array<string, mixed>>  $roltypen
     * @return array<string, mixed>|null
     */
    public static function initiatorRoltype(?MunicipalityZaaktypeMapping $mapping, iterable $roltypen): ?array
    {
        $roltypen = collect($roltypen);

        if ($mapping?->initiator_roltype) {
            $match = $roltypen->first(fn ($r) => ($r['omschrijving'] ?? null) === $mapping->initiator_roltype
                || ($r['omschrijvingGeneriek'] ?? null) === $mapping->initiator_roltype);
            if ($match) {
                return $match;
            }
        }

        return $roltypen->first(fn ($r) => ($r['omschrijvingGeneriek'] ?? null) === 'initiator');
    }

    /**
     * The "Ingetrokken" resultaattype. Heuristic: omschrijvingGeneriek === 'Ingetrokken'.
     *
     * @param  iterable<array<string, mixed>>  $resultaattypen
     * @return array<string, mixed>|null
     */
    public static function ingetrokkenResultaattype(?MunicipalityZaaktypeMapping $mapping, iterable $resultaattypen): ?array
    {
        $resultaattypen = collect($resultaattypen);

        if ($mapping?->ingetrokken_resultaattype) {
            $match = $resultaattypen->first(fn ($r) => ($r['omschrijving'] ?? null) === $mapping->ingetrokken_resultaattype
                || ($r['omschrijvingGeneriek'] ?? null) === $mapping->ingetrokken_resultaattype);
            if ($match) {
                return $match;
            }
        }

        return $resultaattypen->firstWhere('omschrijvingGeneriek', 'Ingetrokken');
    }

    /**
     * The informatieobjecttype for uploaded attachments.
     *
     * @template TType of object
     *
     * @param  Collection<int, TType>  $types
     * @param  bool  $matchBijlageInOmschrijving  When no blueprint match: prefer a type
     *                                            whose omschrijving contains "bijlage"
     *                                            before falling back to the first type.
     * @return TType|null
     */
    public static function bijlageInformatieobjecttype(?MunicipalityZaaktypeMapping $mapping, Collection $types, bool $matchBijlageInOmschrijving = true): ?object
    {
        if ($mapping?->bijlage_informatieobjecttype) {
            $match = $types->first(fn ($type) => property_exists($type, 'omschrijving')
                && $type->omschrijving === $mapping->bijlage_informatieobjecttype);
            if ($match) {
                return $match;
            }
        }

        if ($matchBijlageInOmschrijving) {
            $match = $types->first(fn ($type) => property_exists($type, 'omschrijving')
                && str_contains(mb_strtolower((string) $type->omschrijving), 'bijlage'));
            if ($match) {
                return $match;
            }
        }

        return $types->first();
    }

    /**
     * The informatieobjecttype for the aanvraagformulier PDF.
     *
     * Heuristic when no blueprint match: the first type (the historical PDF
     * fallback, without the "bijlage" omschrijving preference).
     *
     * @template TType of object
     *
     * @param  Collection<int, TType>  $types
     * @return TType|null
     */
    public static function aanvraagInformatieobjecttype(?MunicipalityZaaktypeMapping $mapping, Collection $types): ?object
    {
        if ($mapping?->aanvraag_informatieobjecttype) {
            $match = $types->first(fn ($type) => property_exists($type, 'omschrijving')
                && $type->omschrijving === $mapping->aanvraag_informatieobjecttype);
            if ($match) {
                return $match;
            }
        }

        return $types->first();
    }
}
