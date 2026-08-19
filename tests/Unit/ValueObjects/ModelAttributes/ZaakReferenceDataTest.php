<?php

declare(strict_types=1);

use App\ValueObjects\ModelAttributes\ZaakReferenceData;

/**
 * A zaaktype does not have to carry the start_evenement/eind_evenement
 * eigenschappen, so the value object has to accept their absence.
 */
test('constructs without start and eind evenement', function () {
    $reference = new ZaakReferenceData(
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: 'https://zgw.example.com/catalogi/api/v1/statustypen/1',
    );

    expect($reference->start_evenement)->toBeNull()
        ->and($reference->eind_evenement)->toBeNull()
        ->and($reference->start_evenement_datetime)->toBeNull()
        ->and($reference->eind_evenement_datetime)->toBeNull()
        ->and($reference->registratiedatum_datetime->toIso8601String())->toBe('2026-06-01T10:00:00+02:00');
});

test('null dates survive a toArray roundtrip through the cast', function () {
    $reference = new ZaakReferenceData(
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: '',
        naam_evenement: 'Buurtfeest',
    );

    $roundtripped = new ZaakReferenceData(...json_decode((string) json_encode($reference->toArray()), true));

    expect($reference->toArray())->toEqual($roundtripped->toArray())
        ->and($roundtripped->start_evenement)->toBeNull()
        ->and($roundtripped->eind_evenement)->toBeNull()
        ->and($roundtripped->naam_evenement)->toBe('Buurtfeest');
});

test('toArray keeps emitting the date keys so existing rows keep their shape', function () {
    $reference = new ZaakReferenceData(
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: '',
    );

    expect($reference->toArray())->toHaveKeys(['start_evenement', 'eind_evenement'])
        ->and($reference->toArray()['start_evenement'])->toBeNull();
});

test('dates that are present are still parsed', function () {
    $reference = new ZaakReferenceData(
        start_evenement: '2026-07-01T10:00:00+02:00',
        eind_evenement: '2026-07-01T18:00:00+02:00',
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: '',
    );

    expect($reference->start_evenement_datetime?->toIso8601String())->toBe('2026-07-01T10:00:00+02:00')
        ->and($reference->eind_evenement_datetime?->toIso8601String())->toBe('2026-07-01T18:00:00+02:00');
});
