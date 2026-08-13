<?php

declare(strict_types=1);

/**
 * Per-day times of a multi-day event must survive the whole trip: from the
 * FormState into the case reference_data, and stay there when a single other
 * field on the case is updated.
 *
 * That last part is not theoretical: `ZaakReferenceData::toArray()` is a hard
 * whitelist, so a key missing from it disappears silently on every save.
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;
use App\Models\Zaak;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

function meerdaagseState(): FormState
{
    return new FormState(values: [
        'watIsDeNaamVanHetEvenementVergunning' => 'Driedaags festival',
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-07 02:00',
        'EvenementDagen' => [
            '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
            '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
            '2026-07-06' => ['datum' => '2026-07-06', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
        ],
    ]);
}

test('de dagtijden van een meerdaags evenement landen in de reference_data', function () {
    $reference = app(MapFormStateToReferenceData::class)
        ->build(meerdaagseState(), 'Ingediend', 'https://example.test/statustypen/1');

    expect($reference->dagen_evenement)->toHaveCount(3);
    expect($reference->dagen_evenement[0]['datum'])->toBe('2026-07-04');

    // The 02:00 end time belongs to the night after the first day.
    expect(Carbon\CarbonImmutable::parse($reference->dagen_evenement[0]['eind'])->toDateTimeString())
        ->toBe('2026-07-05 02:00:00');

    // The envelope stays the head and tail of the whole period.
    expect(Carbon\CarbonImmutable::parse($reference->start_evenement)->toDateTimeString())
        ->toBe('2026-07-04 16:00:00');
    expect(Carbon\CarbonImmutable::parse($reference->eind_evenement)->toDateTimeString())
        ->toBe('2026-07-07 02:00:00');
});

test('een eendaags evenement krijgt geen dagtijden', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-05 02:00',
    ]);

    $reference = app(MapFormStateToReferenceData::class)
        ->build($state, 'Ingediend', 'https://example.test/statustypen/1');

    expect($reference->dagen_evenement)->toBeNull();
    expect($reference->dagen_opbouw)->toBeNull();
    expect($reference->dagen_afbouw)->toBeNull();
});

test('opbouw en afbouw krijgen hun eigen dagtijden', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-04 23:00',
        'OpbouwStart' => '2026-07-02 08:00',
        'OpbouwEind' => '2026-07-03 18:00',
        'OpbouwDagen' => [
            '2026-07-02' => ['datum' => '2026-07-02', 'startTijd' => '08:00', 'eindTijd' => '17:00'],
            '2026-07-03' => ['datum' => '2026-07-03', 'startTijd' => '08:00', 'eindTijd' => '18:00'],
        ],
        'AfbouwStart' => '2026-07-05 08:00',
        'AfbouwEind' => '2026-07-06 16:00',
        'AfbouwDagen' => [
            '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '08:00', 'eindTijd' => '17:00'],
            '2026-07-06' => ['datum' => '2026-07-06', 'startTijd' => '08:00', 'eindTijd' => '16:00'],
        ],
    ]);

    $reference = app(MapFormStateToReferenceData::class)
        ->build($state, 'Ingediend', 'https://example.test/statustypen/1');

    expect($reference->dagen_opbouw)->toHaveCount(2);
    expect($reference->dagen_afbouw)->toHaveCount(2);
    expect($reference->dagen_evenement)->toBeNull();
});

test('de dagtijden overleven een gedeeltelijke update van de zaak', function () {
    $reference = app(MapFormStateToReferenceData::class)
        ->build(meerdaagseState(), 'Ingediend', 'https://example.test/statustypen/1');

    $zaak = Zaak::factory()->create(['reference_data' => $reference]);

    // This is the shape the individual case actions use: spread the existing
    // reference_data and overwrite a single key.
    $zaak->reference_data = new ZaakReferenceData(
        ...array_merge($zaak->reference_data->toArray(), ['intern_zaaknummer' => 'INT-42'])
    );
    $zaak->save();

    $opnieuw = $zaak->fresh();

    expect($opnieuw->reference_data->intern_zaaknummer)->toBe('INT-42');
    expect($opnieuw->reference_data->dagen_evenement)->toHaveCount(3);
});
