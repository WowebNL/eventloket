<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Determines the calendar days an event (or its build-up / tear-down) spans.
 *
 * An end time that falls in the small hours belongs to the evening before it,
 * not to a new day: an event running 16:00–02:00 is a single-day event with a
 * late finish. Only when the end moment passes the night boundary of the
 * following morning does the event become genuinely multi-day.
 *
 * The boundary is 06:00. Anything at or before 06:00 counts towards the
 * previous day; anything after it starts a new day of its own.
 */
final class EventDagen
{
    /**
     * Everything up to and including this clock time counts towards the
     * previous calendar day.
     */
    public const NACHT_GRENS = '06:00:00';

    /**
     * The calendar day an end moment belongs to. An end at or before the night
     * boundary is attributed to the day before.
     *
     * Never returns a date before the given start date, so a malformed pair
     * (end before start) still yields at least one day.
     */
    public static function effectieveEinddatum(mixed $start, mixed $eind): ?CarbonImmutable
    {
        $startMoment = self::parse($start);
        $eindMoment = self::parse($eind);

        if ($startMoment === null || $eindMoment === null) {
            return null;
        }

        $datum = CarbonImmutable::parse($eindMoment->toDateTimeString())->startOfDay();

        if (self::valtInDeNacht($eindMoment)) {
            $datum = $datum->subDay();
        }

        $startDatum = CarbonImmutable::parse($startMoment->toDateTimeString())->startOfDay();

        return $datum->lessThan($startDatum) ? $startDatum : $datum;
    }

    /**
     * Whether the period covers more than one calendar day under the night-
     * boundary rule.
     */
    public static function isMeerdaags(mixed $start, mixed $eind): bool
    {
        return count(self::dagen($start, $eind)) > 1;
    }

    /**
     * The calendar days the period covers, from the start date up to and
     * including the effective end date. Returns an empty list when either
     * moment is missing or unparseable.
     *
     * @return list<CarbonImmutable>
     */
    public static function dagen(mixed $start, mixed $eind): array
    {
        $startMoment = self::parse($start);
        $eindDatum = self::effectieveEinddatum($start, $eind);

        if ($startMoment === null || $eindDatum === null) {
            return [];
        }

        $dag = CarbonImmutable::parse($startMoment->toDateTimeString())->startOfDay();

        $dagen = [];
        while ($dag->lessThanOrEqualTo($eindDatum)) {
            $dagen[] = $dag;
            $dag = $dag->addDay();
        }

        return $dagen;
    }

    /**
     * The moment a day block ends. An end time at or before the start time
     * rolls over to the next morning, so 16:00–02:00 ends the following night.
     */
    public static function blokEinde(mixed $datum, ?string $startTijd, ?string $eindTijd): ?CarbonImmutable
    {
        $dag = self::parse($datum)?->startOfDay();

        if ($dag === null || $startTijd === null || $eindTijd === null) {
            return null;
        }

        $eind = self::opDag($dag, $eindTijd);
        $start = self::opDag($dag, $startTijd);

        if ($eind === null || $start === null) {
            return null;
        }

        return $eind->lessThanOrEqualTo($start) ? $eind->addDay() : $eind;
    }

    /**
     * The moment a day block starts.
     */
    public static function blokStart(mixed $datum, ?string $startTijd): ?CarbonImmutable
    {
        $dag = self::parse($datum)?->startOfDay();

        if ($dag === null || $startTijd === null) {
            return null;
        }

        return self::opDag($dag, $startTijd);
    }

    /**
     * Whether an end time that rolls over to the next day stays within the
     * night boundary. A block may run into the small hours, but not through
     * the whole of the next day.
     */
    public static function rolloverBinnenNachtGrens(?string $startTijd, ?string $eindTijd): bool
    {
        if ($startTijd === null || $eindTijd === null) {
            return true;
        }

        $start = self::normaliseerTijd($startTijd);
        $eind = self::normaliseerTijd($eindTijd);

        if ($start === null || $eind === null) {
            return true;
        }

        if ($eind > $start) {
            return true;
        }

        return $eind <= self::NACHT_GRENS;
    }

    private static function valtInDeNacht(CarbonInterface $moment): bool
    {
        return $moment->format('H:i:s') <= self::NACHT_GRENS;
    }

    private static function opDag(CarbonImmutable $dag, string $tijd): ?CarbonImmutable
    {
        $genormaliseerd = self::normaliseerTijd($tijd);

        if ($genormaliseerd === null) {
            return null;
        }

        [$uur, $minuut, $seconde] = array_map(intval(...), explode(':', $genormaliseerd));

        return $dag->setTime($uur, $minuut, $seconde);
    }

    /**
     * Accepts `H:i` and `H:i:s` and returns a comparable `H:i:s` string.
     */
    private static function normaliseerTijd(string $tijd): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($tijd), $delen) !== 1) {
            return null;
        }

        $uur = (int) $delen[1];
        $minuut = (int) $delen[2];
        $seconde = (int) ($delen[3] ?? 0);

        if ($uur > 23 || $minuut > 59 || $seconde > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $uur, $minuut, $seconde);
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        $parsed = SafeDateTime::parse($value);

        if ($parsed === null) {
            return null;
        }

        return CarbonImmutable::parse($parsed->toDateTimeString());
    }
}
