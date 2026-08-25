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

test('locaties_evenement combineert gebouw, kaart en route tot kommagescheiden string', function () {
    $state = new FormState(values: [
        'adresVanDeGebouwEn' => [
            ['naamVanDeLocatieGebouw' => 'Sporthal Noord'],
            ['naamVanDeLocatieGebouw' => 'Gymzaal West'],
        ],
        'naamVanDeLocatieKaart' => 'Stadspark',
        'naamVanDeRoute' => 'Route Centrum',
    ]);

    $plat = collect($this->map->buildEigenschappen($state))->mapWithKeys(fn ($e) => $e)->all();

    expect($plat)->toHaveKey('locaties_evenement', 'Sporthal Noord, Gymzaal West, Stadspark, Route Centrum');
});

test('locaties_evenement met alleen gebouwen', function () {
    $state = new FormState(values: [
        'adresVanDeGebouwEn' => [
            ['naamVanDeLocatieGebouw' => 'Cultuurcentrum'],
        ],
    ]);

    $plat = collect($this->map->buildEigenschappen($state))->mapWithKeys(fn ($e) => $e)->all();

    expect($plat)->toHaveKey('locaties_evenement', 'Cultuurcentrum');
});

test('locaties_evenement ontbreekt als geen locatienamen zijn ingevuld', function () {
    $state = new FormState(values: [
        'adresVanDeGebouwEn' => [
            ['naamVanDeLocatieGebouw' => ''],
        ],
        'naamVanDeLocatieKaart' => null,
        'naamVanDeRoute' => '',
    ]);

    $naden = collect($this->map->buildEigenschappen($state))->map(fn ($e) => key($e))->all();

    expect($naden)->not->toContain('locaties_evenement');
});

test('locaties_evenement ontbreekt als geen locatie-velden zijn opgegeven', function () {
    $state = FormState::empty();

    $naden = collect($this->map->buildEigenschappen($state))->map(fn ($e) => key($e))->all();

    expect($naden)->not->toContain('locaties_evenement');
});

// --- Location-state leak (ZGW/PDF counterpart of #493) ---------------------
//
// Unticking a location kind hides its field but leaves the value in the state
// (Filament keeps a hidden field's value). A copied application therefore still
// carries the source event's location. These tests prove that leftover state of
// an unticked kind no longer reaches the zaakeigenschappen, while a ticked kind
// (or an unanswered question, which counts as "no opinion") is unchanged.

test('event_location laat een afgevinkte gebouw/buiten-soort niet lekken', function () {
    // Alleen "route" aangevinkt, maar gebouw-adres en buiten-vlak staan nog in
    // de state (overgekopieerd uit een eerder evenement).
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'adresVanDeGebouwEn' => ['uuid-1' => ['postcode' => '6211AB', 'huisnummer' => '1']],
        'locatieSOpKaart' => ['type' => 'MultiPolygon', 'coordinates' => [[[[5, 50]]]]],
        'routesOpKaart' => ['type' => 'LineString', 'coordinates' => [[5, 50], [5.1, 50.1]]],
    ]);

    $location = $this->map->buildEventLocation($state);

    expect($location)->toHaveKey('line')
        ->and($location)->not->toHaveKey('bag_addresses')
        ->and($location)->not->toHaveKey('multipolygons');
});

test('locaties_evenement laat de naam van een afgevinkte soort niet lekken', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'adresVanDeGebouwEn' => [['naamVanDeLocatieGebouw' => 'Sporthal Noord']],
        'naamVanDeLocatieKaart' => 'Stadspark',
        'naamVanDeRoute' => 'Route Centrum',
    ]);

    $plat = collect($this->map->buildEigenschappen($state))->mapWithKeys(fn ($e) => $e)->all();

    expect($plat)->toHaveKey('locaties_evenement', 'Route Centrum');
});

test('regressie-anker: met alle waarde-dragende soorten aangevinkt blijft de output identiek', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['gebouw', 'buiten', 'route'],
        'adresVanDeGebouwEn' => [
            ['naamVanDeLocatieGebouw' => 'Sporthal Noord'],
            ['naamVanDeLocatieGebouw' => 'Gymzaal West'],
        ],
        'locatieSOpKaart' => ['type' => 'MultiPolygon', 'coordinates' => [[[[5, 50]]]]],
        'routesOpKaart' => ['type' => 'LineString', 'coordinates' => [[5, 50], [5.1, 50.1]]],
        'naamVanDeLocatieKaart' => 'Stadspark',
        'naamVanDeRoute' => 'Route Centrum',
        'watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt' => 'Vrijthof',
    ]);

    $plat = collect($this->map->buildEigenschappen($state))->mapWithKeys(fn ($e) => $e)->all();

    expect($plat)->toHaveKey('locaties_evenement', 'Sporthal Noord, Gymzaal West, Stadspark, Route Centrum')
        ->and($this->map->buildEventLocation($state))->toBe([
            'multipolygons' => ['type' => 'MultiPolygon', 'coordinates' => [[[[5, 50]]]]],
            'line' => ['type' => 'LineString', 'coordinates' => [[5, 50], [5.1, 50.1]]],
            'bag_addresses' => [
                ['naamVanDeLocatieGebouw' => 'Sporthal Noord'],
                ['naamVanDeLocatieGebouw' => 'Gymzaal West'],
            ],
            'name' => 'Vrijthof',
        ]);
});

test('geen mening: zonder waarVindtHetEvenementPlaats telt elke soort mee (ongewijzigd)', function () {
    // Exact het bestaande event_location-scenario, maar nu expliciet als anker
    // voor het "geen mening"-contract: een afwezige vraag mag niets wissen.
    $state = new FormState(values: [
        'locatieSOpKaart' => ['type' => 'MultiPolygon', 'coordinates' => [[[[5, 50]]]]],
        'routesOpKaart' => ['type' => 'LineString', 'coordinates' => [[5, 50], [5.1, 50.1]]],
        'adresVanDeGebouwEn' => ['uuid-1' => ['postcode' => '6211AB', 'huisnummer' => '1']],
        'watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt' => 'Vrijthof',
    ]);

    expect($this->map->buildEventLocation($state))->toHaveKeys(['multipolygons', 'line', 'bag_addresses', 'name']);
});
