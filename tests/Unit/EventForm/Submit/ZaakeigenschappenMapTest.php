<?php

/**
 * `ZaakeigenschappenMap` bouwt drie payloads op basis van een FormState:
 *
 *   1. Een lijst zaakeigenschappen — exact het formaat dat OF in Objects
 *      API zette (`[{naam: waarde}, ...]`), met de 11 eigenschap-namen
 *      zoals gevonden in de OF-registratie-backends.
 *   2. Een initiator-blok met KvK/organisatie-naam + contactpersoon.
 *   3. Een event_location-blok (multipolygons/line/bag_addresses) dat
 *      gebruikt wordt door `AddGeometryZGW` en `CreateDoorkomstZaken`.
 *
 * De mapping-keuzes staan vast (geen heuristiek). Deze tests borgen dat
 * het resultaat identiek blijft aan wat OF produceerde zodat downstream
 * jobs precies hetzelfde blijven werken.
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;

beforeEach(function () {
    $this->map = new ZaakeigenschappenMap;
});

test('de 11 OF-eigenschappen worden als {naam: waarde}-entries geëmitteerd', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'OpbouwStart' => '2026-06-14T12:00',
        'OpbouwEind' => '2026-06-14T13:30',
        'AfbouwStart' => '2026-06-14T18:00',
        'AfbouwEind' => '2026-06-14T19:30',
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest Testlaan',
        'soortEvenement' => 'Buurtfeest',
        'aantalVerwachteAanwezigen' => 80,
        'risicoClassificatie' => 'A',
    ]);

    $eigenschappen = $this->map->buildEigenschappen($state);

    // Platteer tot [naam => waarde] voor simpele asserts.
    $plat = collect($eigenschappen)->mapWithKeys(fn ($e) => $e)->all();

    expect($plat)->toMatchArray([
        'start_evenement' => '2026-06-14T14:00',
        'eind_evenement' => '2026-06-14T18:00',
        'start_opbouw' => '2026-06-14T12:00',
        'eind_opbouw' => '2026-06-14T13:30',
        'start_afbouw' => '2026-06-14T18:00',
        'eind_afbouw' => '2026-06-14T19:30',
        'naam_evenement' => 'Buurtfeest Testlaan',
        'types_evenement' => 'Buurtfeest',
        'aanwezigen' => 80,
        'risico_classificatie' => 'A',
    ]);
});

test('lege waarden worden weggelaten (OF sloeg die ook over)', function () {
    $state = new FormState(values: [
        'EvenementStart' => '2026-06-14T14:00',
        'watIsDeNaamVanHetEvenementVergunning' => '',
        'risicoClassificatie' => null,
        'soortEvenement' => [],
    ]);

    $eigenschappen = $this->map->buildEigenschappen($state);
    $naden = collect($eigenschappen)->map(fn ($e) => key($e))->all();

    expect($naden)->toContain('start_evenement')
        ->and($naden)->not->toContain('naam_evenement')
        ->and($naden)->not->toContain('risico_classificatie')
        ->and($naden)->not->toContain('types_evenement');
});

test('initiator-blok bevat KvK en contactpersoon', function () {
    $state = new FormState(values: [
        'watIsUwVoornaam' => 'Noah',
        'watIsUwAchternaam' => 'de Graaf',
        'watIsUwEMailadres' => 'noah@example.net',
        'watIsUwTelefoonnummer' => '06-1234',
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'watIsDeNaamVanUwOrganisatie' => 'Media Tuin',
    ]);

    $initiator = $this->map->buildInitiator($state);

    expect($initiator['kvk'])->toBe('12345678')
        ->and($initiator['organisatie_naam'])->toBe('Media Tuin')
        ->and($initiator['contactpersoon']['naam'])->toBe('Noah de Graaf')
        ->and($initiator['contactpersoon']['emailadres'])->toBe('noah@example.net')
        ->and($initiator['contactpersoon']['telefoonnummer'])->toBe('06-1234');
});

test('initiator zonder voornaam+achternaam heeft géén lege "naam"-entry', function () {
    $state = new FormState(values: [
        'watIsUwEMailadres' => 'test@example.net',
    ]);

    $initiator = $this->map->buildInitiator($state);

    // contactpersoon-subarray bestaat alleen met ingevulde keys — zonder
    // naam-velden zit er maximaal emailadres in.
    expect($initiator['contactpersoon'])->not->toHaveKey('naam');
});

test('event_location-blok neemt line+multipolygons+bag_addresses mee', function () {
    $state = new FormState(values: [
        'locatieSOpKaart' => ['type' => 'MultiPolygon', 'coordinates' => [[[[5, 50]]]]],
        'routesOpKaart' => ['type' => 'LineString', 'coordinates' => [[5, 50], [5.1, 50.1]]],
        'adresVanDeGebouwEn' => ['uuid-1' => ['postcode' => '6211AB', 'huisnummer' => '1']],
        'watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt' => 'Vrijthof',
    ]);

    $location = $this->map->buildEventLocation($state);

    expect($location)->toHaveKeys(['multipolygons', 'line', 'bag_addresses', 'name'])
        ->and($location['name'])->toBe('Vrijthof');
});

test('lege event_location → lege array', function () {
    $state = FormState::empty();

    expect($this->map->buildEventLocation($state))->toBe([]);
});
