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
     * The largest number of calendar days one period may span.
     *
     * This is a functional limit on how long an event (or its build-up or
     * tear-down) is allowed to run, not a technical one. It doubles as the
     * upper bound of the day list, so an end date outside the range the form is
     * meant to accept can never drive an unbounded loop.
     */
    public const MAX_DAGEN = 31;

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
     * Whether the period spans more calendar days than one period is allowed to
     * cover. Answers in constant time and without building the day list, so it
     * stays cheap for an end date far outside the intended range.
     */
    public static function overschrijdtMaximum(mixed $start, mixed $eind): bool
    {
        return self::spanInDagen($start, $eind) > self::MAX_DAGEN;
    }

    /**
     * The calendar days the period covers, from the start date up to and
     * including the effective end date. Returns an empty list when either
     * moment is missing or unparseable.
     *
     * The list never grows past {@see self::MAX_DAGEN} days. Clipping a longer
     * period is deliberate: it keeps reporting itself as multi-day and still
     * shows day rows, so the period stays visible and the duration rule on the
     * form can explain what is wrong. Returning an empty list instead would
     * make isMeerdaags() call a months-long period single-day and make the day
     * rows vanish without a word.
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

        $eersteDag = CarbonImmutable::parse($startMoment->toDateTimeString())->startOfDay();
        $aantal = min(self::kalenderdagenTussen($eersteDag, $eindDatum), self::MAX_DAGEN);

        $dagen = [];
        for ($nummer = 0; $nummer < $aantal; $nummer++) {
            $dagen[] = $eersteDag->addDays($nummer);
        }

        return $dagen;
    }

    /**
     * The number of calendar days the period would cover if it were not capped,
     * or zero when either moment is missing or unparseable.
     */
    private static function spanInDagen(mixed $start, mixed $eind): int
    {
        $startMoment = self::parse($start);
        $eindDatum = self::effectieveEinddatum($start, $eind);

        if ($startMoment === null || $eindDatum === null) {
            return 0;
        }

        $eersteDag = CarbonImmutable::parse($startMoment->toDateTimeString())->startOfDay();

        return self::kalenderdagenTussen($eersteDag, $eindDatum);
    }

    /**
     * Calendar days from one day up to and including another, both already at
     * the start of their day. Counted on the dates alone, so a clock change in
     * between cannot shorten or lengthen the count. Always at least one:
     * effectieveEinddatum() never returns a day before the start.
     */
    private static function kalenderdagenTussen(CarbonImmutable $eersteDag, CarbonImmutable $laatsteDag): int
    {
        $eerste = new \DateTimeImmutable($eersteDag->toDateString(), new \DateTimeZone('UTC'));
        $laatste = new \DateTimeImmutable($laatsteDag->toDateString(), new \DateTimeZone('UTC'));

        return (int) $eerste->diff($laatste)->days + 1;
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
