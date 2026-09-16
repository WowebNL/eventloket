<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\EventForm\State\FormState;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Carbon\CarbonImmutable;

/**
 * The single source for the "Overzicht ingevulde tijden" table, shared by the
 * wizard summary, the submission PDF and the case infolist so all three tell
 * the same story.
 *
 * A single-day period stays one row holding two full moments. A multi-day
 * period expands into one row per day holding only clock times, since the day
 * itself is already in the row label.
 */
final class TijdenOverzicht
{
    /**
     * Activity label, envelope start key, envelope end key, per-day repeater key.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const BLOKKEN = [
        ['Opbouw', 'OpbouwStart', 'OpbouwEind', 'OpbouwDagen'],
        ['Publiek', 'EvenementStart', 'EvenementEind', 'EvenementDagen'],
        ['Afbouw', 'AfbouwStart', 'AfbouwEind', 'AfbouwDagen'],
    ];

    /**
     * Activity label, envelope start key, envelope end key, stored day blocks key.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const REFERENCE_BLOKKEN = [
        ['Opbouw', 'start_opbouw', 'eind_opbouw', 'dagen_opbouw'],
        ['Publiek', 'start_evenement', 'eind_evenement', 'dagen_evenement'],
        ['Afbouw', 'start_afbouw', 'eind_afbouw', 'dagen_afbouw'],
    ];

    /**
     * @return list<list<string>> rows of [activiteit, start, eind]
     */
    public static function uitFormState(FormState $state): array
    {
        $rijen = [];

        foreach (self::BLOKKEN as [$label, $startKey, $eindKey, $dagenKey]) {
            $dagBlokken = DagenRepeater::naarReferenceData(
                is_array($dagen = $state->get($dagenKey)) ? $dagen : [],
            );

            $rijen = [...$rijen, ...self::rijenVoorBlok(
                $label,
                $state->get($startKey),
                $state->get($eindKey),
                $dagBlokken,
            )];
        }

        return $rijen;
    }

    /**
     * @return list<list<string>> rows of [activiteit, start, eind]
     */
    public static function uitReferenceData(ZaakReferenceData $reference): array
    {
        $rijen = [];

        foreach (self::REFERENCE_BLOKKEN as [$label, $startKey, $eindKey, $dagenKey]) {
            $rijen = [...$rijen, ...self::rijenVoorBlok(
                $label,
                $reference->{$startKey},
                $reference->{$eindKey},
                $reference->{$dagenKey} ?? [],
            )];
        }

        return $rijen;
    }

    /**
     * @param  mixed  $dagBlokken  stored day blocks, if the period runs over several days
     * @return list<list<string>>
     */
    private static function rijenVoorBlok(string $label, mixed $start, mixed $eind, mixed $dagBlokken): array
    {
        $dagRijen = DagenRepeater::alsTabelRijen($dagBlokken);

        if ($dagRijen !== []) {
            return array_map(
                fn (array $rij): array => [$label.' — '.$rij['datum'], $rij['start'], $rij['eind']],
                $dagRijen,
            );
        }

        $startLabel = self::moment($start);
        $eindLabel = self::moment($eind);

        if ($startLabel === '' && $eindLabel === '') {
            return [];
        }

        return [[$label, $startLabel, $eindLabel]];
    }

    private static function moment(mixed $waarde): string
    {
        if (! is_string($waarde) || trim($waarde) === '') {
            return '';
        }

        try {
            return CarbonImmutable::parse($waarde, 'Europe/Amsterdam')->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return '';
        }
    }
}
