<?php

declare(strict_types=1);

/**
 * The calendar library converts a time that carries a UTC offset with the
 * browser's offset of today, so an event in the other half of the year
 * (summer time against winter time) would move by an hour. Zaak events are
 * therefore sent as wall clock time in the application timezone, without an
 * offset, which the calendar shows as is.
 */

use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

dataset('event dates', [
    'summer time' => ['2026-07-10T10:00:00+02:00', '2026-07-10T18:30:00+02:00', '2026-07-10T10:00:00', '2026-07-10T18:30:00'],
    'winter time' => ['2026-11-10T10:00:00+01:00', '2026-11-10T18:30:00+01:00', '2026-11-10T10:00:00', '2026-11-10T18:30:00'],
    'stored in utc' => ['2026-07-10T08:00:00+00:00', '2026-07-10T16:30:00+00:00', '2026-07-10T10:00:00', '2026-07-10T18:30:00'],
]);

test('a zaak is sent to the calendar as wall clock time in the application timezone', function (string $start, string $end, string $expectedStart, string $expectedEnd) {
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-06-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: 'https://zgw.example.test/catalogi/api/v1/statustypen/1',
            start_evenement: $start,
            eind_evenement: $end,
            naam_evenement: 'Synthetic event',
        ),
    ]);

    // The browser offset that the calendar sends along must not matter.
    foreach ([60, 120, 0] as $browserOffset) {
        $object = $zaak->toCalendarEvent()->toCalendarObject($browserOffset, true);

        expect($object['start'])->toBe($expectedStart)
            ->and($object['end'])->toBe($expectedEnd);
    }
})->with('event dates');
