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
     * Re-key eigenschappen read back from ZGW onto their logical FormState keys.
     *
     * The mirror image of {@see self::eigenschapNaam()}. A ZGW backend may name
     * its eigenschappen differently per organisation, so a value read back under
     * a mapped naam has to be translated back before it can be merged into the
     * application's own reference data; without that step the value is filed
     * under a name nothing reads and the change is lost.
     *
     * Names the blueprint does not mention keep their own name, which is the
     * identity case for a catalogus that uses the logical keys verbatim.
     *
     * @param  array<string, mixed>  $eigenschappen  keyed by ZGW eigenschap naam
     * @return array<string, mixed> keyed by logical FormState key
     */
    public static function logicalEigenschappen(?MunicipalityZaaktypeMapping $mapping, array $eigenschappen): array
    {
        $byNaam = [];
        foreach ($mapping->eigenschap_map ?? [] as $logicalKey => $naam) {
            // Identity entries add nothing, and the first mapping of a naam wins
            // so a duplicate selector cannot make the result order-dependent.
            if (is_string($naam) && $naam !== '' && $naam !== (string) $logicalKey && ! isset($byNaam[$naam])) {
                $byNaam[$naam] = (string) $logicalKey;
            }
        }

        $untranslated = [];
        $translated = [];
        foreach ($eigenschappen as $naam => $waarde) {
            if (isset($byNaam[$naam])) {
                $translated[$byNaam[$naam]] = $waarde;
            } else {
                $untranslated[$naam] = $waarde;
            }
        }

        // Translated values are applied last: an explicitly mapped eigenschap
        // outranks a stray one that happens to carry the logical key as its name.
        return array_merge($untranslated, $translated);
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
