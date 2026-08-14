<?php

declare(strict_types=1);

/**
 * The "Overzicht ingevulde tijden" table is shown in three places: at the
 * bottom of the Tijden step, in the submission PDF and on the case. All three
 * read from this one source so they cannot drift apart.
 */

use App\EventForm\State\FormState;
use App\EventForm\Support\TijdenOverzicht;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;

test('een eendaags evenement blijft één regel per activiteit', function () {
    $rijen = TijdenOverzicht::uitFormState(new FormState(values: [
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-05 02:00',
    ]));

    expect($rijen)->toHaveCount(1);
    expect($rijen[0][0])->toBe('Publiek');
    expect($rijen[0][1])->toContain('16:00');
});

test('een meerdaags evenement krijgt een regel per dag', function () {
    $rijen = TijdenOverzicht::uitFormState(new FormState(values: [
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-06 23:00',
        'EvenementDagen' => [
            '2026-07-04' => ['datum' => '2026-07-04', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
            '2026-07-05' => ['datum' => '2026-07-05', 'startTijd' => '16:00', 'eindTijd' => '02:00'],
            '2026-07-06' => ['datum' => '2026-07-06', 'startTijd' => '16:00', 'eindTijd' => '23:00'],
        ],
    ]));

    expect($rijen)->toHaveCount(3);
    expect($rijen[0][0])->toContain('Publiek');
    expect($rijen[0][0])->toContain('4 juli 2026');
    expect($rijen[0][1])->toBe('16:00');
});

test('opbouw, publiek en afbouw staan in die volgorde onder elkaar', function () {
    $rijen = TijdenOverzicht::uitFormState(new FormState(values: [
        'OpbouwStart' => '2026-07-03 08:00',
        'OpbouwEind' => '2026-07-04 12:00',
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-04 23:00',
        'AfbouwStart' => '2026-07-05 08:00',
        'AfbouwEind' => '2026-07-05 12:00',
    ]));

    expect(array_column($rijen, 0))->toBe(['Opbouw', 'Publiek', 'Afbouw']);
});

test('activiteiten zonder ingevulde tijden krijgen geen regel', function () {
    $rijen = TijdenOverzicht::uitFormState(new FormState(values: [
        'EvenementStart' => '2026-07-04 16:00',
        'EvenementEind' => '2026-07-04 23:00',
    ]));

    expect(array_column($rijen, 0))->toBe(['Publiek']);
});

test('de zaakweergave leest dezelfde tabel uit de opgeslagen dagtijden', function () {
    $reference = new ZaakReferenceData(
        start_evenement: '2026-07-04T16:00:00+02:00',
        eind_evenement: '2026-07-07T02:00:00+02:00',
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: '',
        dagen_evenement: [
            ['datum' => '2026-07-04', 'start' => '2026-07-04T16:00:00+02:00', 'eind' => '2026-07-05T02:00:00+02:00'],
            ['datum' => '2026-07-05', 'start' => '2026-07-05T16:00:00+02:00', 'eind' => '2026-07-06T02:00:00+02:00'],
        ],
    );

    $rijen = TijdenOverzicht::uitReferenceData($reference);

    expect($rijen)->toHaveCount(2);
    expect($rijen[0][0])->toContain('4 juli 2026');
    expect($rijen[0][1])->toBe('16:00');
    // A roll-over into the next morning stays visible.
    expect($rijen[0][2])->toContain('5 juli');
});

test('een zaak zonder dagtijden valt terug op de start- en eindmomenten', function () {
    $reference = new ZaakReferenceData(
        start_evenement: '2026-07-04T16:00:00+02:00',
        eind_evenement: '2026-07-04T23:00:00+02:00',
        registratiedatum: '2026-06-01T10:00:00+02:00',
        status_name: 'Ingediend',
        statustype_url: '',
    );

    $rijen = TijdenOverzicht::uitReferenceData($reference);

    expect($rijen)->toHaveCount(1);
    expect($rijen[0][0])->toBe('Publiek');
});
