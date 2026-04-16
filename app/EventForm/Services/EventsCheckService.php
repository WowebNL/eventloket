<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\Models\Zaak;

/**
 * Controleert of er andere evenementen gepland staan in dezelfde gemeente
 * binnen een tijdsperiode. Gebruikt om conflicten te signaleren.
 *
 * Oorspronkelijk OF's `fetch-from-service` naar /api/events/check.
 */
class EventsCheckService
{
    /**
     * @param  string  $startDate  ISO 8601 / date string
     * @param  string  $endDate  ISO 8601 / date string
     * @param  string  $municipalityBrkId  bv. 'GM0882'
     * @return array{event_names: string, event_count: int}
     */
    public function check(string $startDate, string $endDate, string $municipalityBrkId): array
    {
        $zaken = Zaak::query()
            ->where(function ($query) use ($startDate, $endDate): void {
                $query
                    ->whereBetween('reference_data->start_evenement', [$startDate, $endDate])
                    ->orWhereBetween('reference_data->eind_evenement', [$startDate, $endDate]);
            })
            ->whereHas('municipality', function ($query) use ($municipalityBrkId): void {
                $query->where('brk_identification', $municipalityBrkId);
            })
            ->limit(10)
            ->get();

        $names = $zaken->isNotEmpty()
            ? $zaken->pluck('reference_data.naam_evenement')->filter()->join(', ')
            : '';

        return [
            'event_names' => $names,
            'event_count' => $zaken->count(),
        ];
    }
}
