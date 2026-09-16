<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\Models\Zaak;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Checks whether other events are planned in the same municipality within a
 * period. Used to flag conflicts to the organiser.
 *
 * Originally Open Forms' `fetch-from-service` call to /api/events/check.
 */
class EventsCheckService
{
    /**
     * How many names we return at most. The count itself is unlimited, so the
     * message "you have among others these events" names an honest total.
     */
    private const MAX_NAMEN = 10;

    /**
     * @param  string  $startDate  ISO 8601 / date string
     * @param  string  $endDate  ISO 8601 / date string
     * @param  string  $municipalityBrkId  e.g. 'GM0882'
     * @return array{event_names: string, event_count: int}
     */
    public function check(string $startDate, string $endDate, string $municipalityBrkId): array
    {
        $start = $this->parse($startDate);
        $eind = $this->parse($endDate, eindeVanDagBijAlleenDatum: true);

        if ($start === null || $eind === null) {
            return ['event_names' => '', 'event_count' => 0];
        }

        if ($eind->lessThan($start)) {
            [$start, $eind] = [$eind, $start];
        }

        $query = Zaak::query()
            // Two periods overlap when one starts before the other ends, and
            // vice versa. An event spanning the whole given period counts as an
            // overlap too; the old whereBetween variant missed those, since it
            // only checked whether either end fell inside the window.
            //
            // The dates live in a JSON column as ISO 8601 strings, so this is a
            // text comparison. That is deliberate: it keeps the query portable
            // across the database drivers we run on, and a fixed-width ISO 8601
            // string sorts chronologically. The one imprecision is the timezone
            // offset, which can put two moments within an hour of each other on
            // the wrong side of a DST switch. For a "what else is planned around
            // then" signal that is noise rather than a defect.
            ->where('reference_data->start_evenement', '<=', $eind->toIso8601String())
            ->where('reference_data->eind_evenement', '>=', $start->toIso8601String())
            ->whereHas('municipality', function (Builder $query) use ($municipalityBrkId): void {
                $query->where('brk_identification', $municipalityBrkId);
            });

        $namen = (clone $query)
            ->limit(self::MAX_NAMEN)
            ->get()
            ->pluck('reference_data.naam_evenement')
            ->filter()
            ->join(', ');

        return [
            'event_names' => $namen,
            'event_count' => $query->count(),
        ];
    }

    /**
     * A bare date carries no time, so as the end of a window it means the whole
     * of that day rather than the stroke of midnight.
     */
    private function parse(string $waarde, bool $eindeVanDagBijAlleenDatum = false): ?CarbonImmutable
    {
        $waarde = trim($waarde);

        if ($waarde === '') {
            return null;
        }

        try {
            $moment = CarbonImmutable::parse($waarde, 'Europe/Amsterdam');
        } catch (\Throwable) {
            return null;
        }

        if ($eindeVanDagBijAlleenDatum && preg_match('/\d{1,2}:\d{2}/', $waarde) !== 1) {
            return $moment->endOfDay();
        }

        return $moment;
    }
}
