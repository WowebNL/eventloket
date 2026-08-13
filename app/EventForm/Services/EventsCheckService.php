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
     * The dates in `reference_data` are ISO 8601 strings, but older cases can
     * hold another format. Only values that look like ISO are cast to a
     * timestamp; the rest falls outside the comparison instead of blowing up
     * the query.
     */
    private const ISO_PATROON = '^\d{4}-\d{2}-\d{2}';

    /**
     * @param  string  $startDate  ISO 8601 / date string
     * @param  string  $endDate  ISO 8601 / date string
     * @param  string  $municipalityBrkId  e.g. 'GM0882'
     * @return array{event_names: string, event_count: int}
     */
    public function check(string $startDate, string $endDate, string $municipalityBrkId): array
    {
        $start = $this->parse($startDate);
        $eind = $this->parse($endDate);

        if ($start === null || $eind === null) {
            return ['event_names' => '', 'event_count' => 0];
        }

        if ($eind->lessThan($start)) {
            [$start, $eind] = [$eind, $start];
        }

        $query = Zaak::query()
            // Two periods overlap when one starts before the other ends, and
            // vice versa. An event spanning the whole given period counts as
            // an overlap too; the old whereBetween variant missed those,
            // since it only checked whether either end fell inside the window.
            ->whereRaw($this->moment('start_evenement').' <= ?', [$eind])
            ->whereRaw($this->moment('eind_evenement').' >= ?', [$start])
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
     * A JSON date field as a timestamp, or NULL when the stored value does not
     * look like ISO. The CASE keeps the cast from running in that branch, so
     * legacy data cannot produce a database error.
     */
    private function moment(string $key): string
    {
        return sprintf(
            "(CASE WHEN reference_data->>'%s' ~ '%s' THEN (reference_data->>'%s')::timestamptz END)",
            $key,
            self::ISO_PATROON,
            $key,
        );
    }

    private function parse(string $waarde): ?CarbonImmutable
    {
        if (trim($waarde) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($waarde, 'Europe/Amsterdam');
        } catch (\Throwable) {
            return null;
        }
    }
}
