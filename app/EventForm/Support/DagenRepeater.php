<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use Carbon\CarbonImmutable;

/**
 * Translates between the two datetime pickers that bound a period (the
 * envelope) and the per-day rows an organiser fills in for a multi-day period.
 *
 * The envelope stays authoritative: it drives which days exist, and its own
 * start and end time are mirrored onto the first and last row. The rows only
 * add the detail the envelope cannot express, namely when each individual day
 * starts and ends.
 *
 * Row keys are the ISO date of the day, which keeps the sync idempotent: a day
 * that survives a date change keeps the times already filled in for it.
 */
final class DagenRepeater
{
    /**
     * Rebuild the per-day rows for an envelope, preserving times already
     * entered for days that still exist. Returns an empty array for a
     * single-day period, so the repeater simply has nothing to show.
     *
     * @param  array<array-key, mixed>  $bestaand
     * @return array<string, array{datum: string, startTijd: ?string, eindTijd: ?string}>
     */
    public static function sync(mixed $start, mixed $eind, array $bestaand = []): array
    {
        $dagen = EventDagen::dagen($start, $eind);

        if (count($dagen) < 2) {
            return [];
        }

        $bestaandOpDatum = self::indexeerOpDatum($bestaand);

        $startMoment = SafeDateTime::parse($start);
        $eindMoment = SafeDateTime::parse($eind);

        $eersteDatum = $dagen[0]->toDateString();
        $laatsteDatum = $dagen[count($dagen) - 1]->toDateString();

        $rijen = [];

        foreach ($dagen as $dag) {
            $datum = $dag->toDateString();
            $rij = $bestaandOpDatum[$datum] ?? [];

            $startTijd = self::tijdOfNull($rij['startTijd'] ?? null);
            $eindTijd = self::tijdOfNull($rij['eindTijd'] ?? null);

            // The envelope owns the very first start and the very last end;
            // the organiser changes those in the pickers above the repeater.
            if ($datum === $eersteDatum && $startMoment !== null) {
                $startTijd = $startMoment->format('H:i');
            }

            if ($datum === $laatsteDatum && $eindMoment !== null) {
                $eindTijd = $eindMoment->format('H:i');
            }

            $rijen[$datum] = [
                'datum' => $datum,
                'startTijd' => $startTijd,
                'eindTijd' => $eindTijd,
            ];
        }

        return $rijen;
    }

    /**
     * Whether a given row holds the first day of the envelope, whose start
     * time is mirrored from the envelope and therefore not editable.
     */
    public static function isEersteDag(mixed $datum, mixed $start): bool
    {
        $dag = SafeDateTime::parse($datum);
        $startMoment = SafeDateTime::parse($start);

        if ($dag === null || $startMoment === null) {
            return false;
        }

        return $dag->toDateString() === $startMoment->toDateString();
    }

    /**
     * Whether a given row holds the last day of the envelope, whose end time
     * is mirrored from the envelope and therefore not editable.
     */
    public static function isLaatsteDag(mixed $datum, mixed $start, mixed $eind): bool
    {
        $dag = SafeDateTime::parse($datum);
        $laatste = EventDagen::effectieveEinddatum($start, $eind);

        if ($dag === null || $laatste === null) {
            return false;
        }

        return $dag->toDateString() === $laatste->toDateString();
    }

    /**
     * Flatten the repeater rows into the list of resolved day blocks we store
     * on the zaak. Rows without both times are skipped, and an end time at or
     * before the start time rolls over into the next morning.
     *
     * @param  array<array-key, mixed>  $rijen
     * @return list<array{datum: string, start: string, eind: string}>
     */
    public static function naarReferenceData(array $rijen): array
    {
        $blokken = [];

        foreach (self::indexeerOpDatum($rijen) as $datum => $rij) {
            $startTijd = self::tijdOfNull($rij['startTijd'] ?? null);
            $eindTijd = self::tijdOfNull($rij['eindTijd'] ?? null);

            $start = EventDagen::blokStart($datum, $startTijd);
            $eind = EventDagen::blokEinde($datum, $startTijd, $eindTijd);

            if ($start === null || $eind === null) {
                continue;
            }

            $blokken[] = [
                'datum' => $datum,
                'start' => $start->toIso8601String(),
                'eind' => $eind->toIso8601String(),
            ];
        }

        usort($blokken, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $blokken;
    }

    /**
     * Rebuild repeater rows from stored day blocks, for prefilling a copy of
     * an earlier application.
     *
     * @param  mixed  $blokken  the stored `dagen_*` value
     * @return array<string, array{datum: string, startTijd: string, eindTijd: string}>
     */
    public static function uitReferenceData(mixed $blokken): array
    {
        if (! is_array($blokken)) {
            return [];
        }

        $rijen = [];

        foreach ($blokken as $blok) {
            if (! is_array($blok)) {
                continue;
            }

            $start = self::parseOpgeslagen($blok['start'] ?? null);
            $eind = self::parseOpgeslagen($blok['eind'] ?? null);

            if ($start === null || $eind === null) {
                continue;
            }

            $datum = is_string($blok['datum'] ?? null)
                ? $blok['datum']
                : $start->toDateString();

            $rijen[$datum] = [
                'datum' => $datum,
                'startTijd' => $start->format('H:i'),
                'eindTijd' => $eind->format('H:i'),
            ];
        }

        return $rijen;
    }

    /**
     * Render the stored day blocks as table rows for the summary, the PDF and
     * the case infolist.
     *
     * @param  mixed  $blokken  the stored `dagen_*` value
     * @return list<array{datum: string, start: string, eind: string}>
     */
    public static function alsTabelRijen(mixed $blokken, string $datumFormat = 'l j F Y', string $tijdFormat = 'H:i'): array
    {
        if (! is_array($blokken)) {
            return [];
        }

        $rijen = [];

        foreach ($blokken as $blok) {
            if (! is_array($blok)) {
                continue;
            }

            $start = self::parseOpgeslagen($blok['start'] ?? null);
            $eind = self::parseOpgeslagen($blok['eind'] ?? null);

            if ($start === null || $eind === null) {
                continue;
            }

            $eindLabel = $eind->format($tijdFormat);

            // Make a roll-over into the small hours explicit, otherwise a row
            // reading "16:00 – 02:00" is ambiguous about which night it ends.
            if ($eind->toDateString() !== $start->toDateString()) {
                $eindLabel .= ' ('.$eind->translatedFormat('j F').')';
            }

            $rijen[] = [
                'datum' => $start->translatedFormat($datumFormat),
                'start' => $start->format($tijdFormat),
                'eind' => $eindLabel,
            ];
        }

        return $rijen;
    }

    /**
     * Repeater state arrives keyed by row identifier, which is the ISO date
     * for rows we generated but a UUID for anything Filament created itself.
     * Re-key on the row's own `datum` so both shapes line up.
     *
     * @param  array<array-key, mixed>  $rijen
     * @return array<string, array<string, mixed>>
     */
    private static function indexeerOpDatum(array $rijen): array
    {
        $opDatum = [];

        foreach ($rijen as $sleutel => $rij) {
            if (! is_array($rij)) {
                continue;
            }

            $datum = SafeDateTime::parse($rij['datum'] ?? $sleutel)?->toDateString();

            if ($datum === null) {
                continue;
            }

            $opDatum[$datum] = $rij;
        }

        ksort($opDatum);

        return $opDatum;
    }

    /**
     * Stored day blocks hold full ISO 8601 strings including a timezone
     * offset, which the stricter form-input parser deliberately rejects.
     */
    private static function parseOpgeslagen(mixed $waarde): ?CarbonImmutable
    {
        if (! is_string($waarde) || trim($waarde) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($waarde, 'Europe/Amsterdam');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function tijdOfNull(mixed $waarde): ?string
    {
        if (! is_string($waarde) || trim($waarde) === '') {
            return null;
        }

        return trim($waarde);
    }
}
