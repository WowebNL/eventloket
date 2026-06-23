<?php

/**
 * `MapFormStateToReferenceData` vertaalt een ingevulde FormState naar
 * het `ZaakReferenceData`-VO dat op onze lokale `Zaak` wordt opgeslagen.
 *
 * De VO zelf valideert datumformaten en is onveranderlijk. Wat deze
 * klas doet, is puur "pak het juiste FormState-veld, geef 'm aan de VO".
 * Deze tests documenteren die 1-op-1 afbeelding + hoe met missende
 * waarden om te gaan (meeste velden mogen `null` zijn; de datum-velden
 * vallen terug op "nu" via de VO).
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\MapFormStateToReferenceData;

beforeEach(function () {
    $this->map = new MapFormStateToReferenceData;
});

test('complete state → complete ReferenceData met alle velden gevuld', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'OpbouwStart' => '2026-06-14T12:00',
        'OpbouwEind' => '2026-06-14T13:30',
        'AfbouwStart' => '2026-06-14T18:00',
        'AfbouwEind' => '2026-06-14T19:30',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
        'soortEvenement' => 'Buurtfeest',
        'watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX' => '80',
        'risicoClassificatie' => 'A',
        'naamVanDeLocatie' => 'Buurtcentrum De Hoek',
    ]);

    $ref = $this->map->build($state, 'Ingediend', 'https://example.com/statustype/1');

    expect($ref->naam_evenement)->toBe('Buurtfeest Testlaan')
        ->and($ref->types_evenement)->toBe('Buurtfeest')
        ->and($ref->aanwezigen)->toBe('80')
        ->and($ref->risico_classificatie)->toBe('A')
        ->and($ref->naam_locatie_evenement)->toBe('Buurtcentrum De Hoek')
        ->and($ref->status_name)->toBe('Ingediend')
        ->and($ref->statustype_url)->toBe('https://example.com/statustype/1')
        ->and($ref->start_evenement)->toContain('2026-06-14')
        ->and($ref->start_opbouw)->toContain('2026-06-14')
        ->and($ref->eind_afbouw)->toContain('2026-06-14');
});

test('missende optionele velden blijven null', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $ref = $this->map->build($state, 'Ingediend', 'https://example.com/statustype/1');

    expect($ref->naam_evenement)->toBeNull()
        ->and($ref->risico_classificatie)->toBeNull()
        ->and($ref->start_opbouw)->toBeNull()
        ->and($ref->eind_opbouw)->toBeNull();
});

test('gebouw-tak: naam_locatie_evenement uit adresVanDeGebouwEn-repeater', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'adresVanDeGebouwEn' => [
            'uuid-1' => [
                'naamVanDeLocatieGebouw' => 'Sporthal De Geusselt',
                'huisnummer' => '1',
            ],
        ],
    ]);

    $ref = $this->map->build($state, 'Ingediend', '');

    expect($ref->naam_locatie_evenement)->toBe('Sporthal De Geusselt');
});

test('registratiedatum wordt gezet op "nu" bij elke build', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
    ]);

    $voor = now()->subSecond();
    $ref = $this->map->build($state, 'Ingediend', '');
    $na = now()->addSecond();

    expect($ref->registratiedatum_datetime->between($voor, $na))->toBeTrue();
});
