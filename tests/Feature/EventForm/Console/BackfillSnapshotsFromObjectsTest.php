<?php

declare(strict_types=1);

/**
 * Backfill-command: zet de OpenForms-submission die voor oude zaken nog in
 * de Objects API staat (record.data.data) om naar een form_state_snapshot.
 * De secties worden plat geslagen tot {key: value}; losse top-level velden
 * behouden hun key. Idempotent: zaken die al een snapshot hebben blijven met
 * rust, en --dry-run slaat niets op.
 */

use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config(['openzaak.objectsapi.url' => 'https://objects.test/']);
});

function fakeObjectsRecord(): void
{
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                // Stap-sectie met direct herkende form-key ...
                'contactgegevens' => [
                    'watIsUwTelefoonnummer' => '0612345678',
                ],
                // ... een container-fieldset met een genest herkend veld
                // (route → routesOpKaart is een echte form-key) ...
                'locatie-van-het-evenement' => [
                    'route' => [
                        'routesOpKaart' => [['routeVanHetEvenement' => ['type' => 'LineString']]],
                    ],
                ],
                // ... legacy-keys uit de oude formulier-generatie ...
                'vragenboom-2' => [
                    'watIsDeNaamVanHetEvenement' => 'Buurtfeest',
                    'watIsDeStarttijdVanHetEvenement' => '2026-06-01',
                ],
                'vragenboom-3' => [
                    'voornaamIngelogdePersoon' => 'Eva',
                    'aantalGelijktijdigAanwezigePersonen' => '5000',
                ],
                'naam-van-het-evenement' => [
                    'watVoorSoortEvenementIsUwEvenement' => 'muziekevenement',
                    'watIsDeNaamVanDeLocatieSWaarUwEvenementPlaatsvindt' => 'Plein 123',
                ],
                // ... en een onbekend veld dat genegeerd moet worden.
                'losVeld' => 'waarde',
            ]]],
        ], 200),
    ]);
}

function oudeZaak(array $overrides = []): Zaak
{
    return Zaak::factory()->create(array_merge([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'data_object_url' => 'https://objects.test/api/v2/objects/abc-uuid-123',
        'form_state_snapshot' => null,
    ], $overrides));
}

test('extraheert herkende keys recursief, mapt legacy-keys, negeert onbekend', function () {
    fakeObjectsRecord();
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $zaak->refresh();
    $values = $zaak->form_state_snapshot['values'];

    // Direct herkende form-key.
    expect($values)->toHaveKey('watIsUwTelefoonnummer', '0612345678');
    // Genest binnen een container-fieldset (route → routesOpKaart).
    expect($values)->toHaveKey('routesOpKaart');
    // Legacy-keys omgezet naar de nieuwe form-keys.
    expect($values)->toMatchArray([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest',          // was watIsDeNaamVanHetEvenement
        'EvenementStart' => '2026-06-01',                                // was watIsDeStarttijdVanHetEvenement
        'watIsUwVoornaam' => 'Eva',                                      // was voornaamIngelogdePersoon
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '5000',          // was aantalGelijktijdigAanwezigePersonen
        'soortEvenement' => 'muziekevenement',                          // was watVoorSoortEvenementIsUwEvenement
        'naamVanDeLocatie' => 'Plein 123',                              // was watIsDeNaamVanDeLocatieSWaar...
    ]);
    // Onbekend veld wordt niet overgenomen.
    expect($values)->not->toHaveKey('losVeld');
});

test('map-velden: oude Repeater-shape wordt omgezet naar geojson FeatureCollection', function () {
    // OF levert de tekening in de oude geneste shape; het nieuwe Map-veld
    // leest state.geojson.features. Zonder transform blijft de kaart leeg.
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                'locatie-van-het-evenement' => [
                    'locatieSOpKaart' => [[
                        'buitenLocatieVanHetEvenement' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[5.84, 50.90], [5.80, 50.89], [5.86, 50.87], [5.84, 50.90]]],
                        ],
                    ]],
                    'route' => [
                        'routesOpKaart' => [[
                            'routeVanHetEvenement' => [
                                'type' => 'LineString',
                                'coordinates' => [[5.87, 50.90], [5.88, 50.91]],
                            ],
                        ]],
                    ],
                ],
            ]]],
        ], 200),
    ]);
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $zaak->refresh();
    $values = $zaak->form_state_snapshot['values'];

    // Polygon → FeatureCollection met één Polygon-Feature.
    expect($values['locatieSOpKaart']['geojson']['type'])->toBe('FeatureCollection')
        ->and($values['locatieSOpKaart']['geojson']['features'])->toHaveCount(1)
        ->and($values['locatieSOpKaart']['geojson']['features'][0]['geometry']['type'])->toBe('Polygon');
    // LineString → FeatureCollection met één LineString-Feature.
    expect($values['routesOpKaart']['geojson']['features'][0]['geometry']['type'])->toBe('LineString');
});

test('single-select met OF-boolean-map wordt gecoerced naar één scalar (#soortEvenement)', function () {
    // soortEvenement was in het oude OF-formulier een multi-checkbox en is nu
    // een enkele Select. De OF-data is daardoor een boolean-map; zonder
    // coercie krijgt Filament's OptionStateCast een array en crasht bij
    // form->fill() ("Array to string conversion").
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                'naam-van-het-evenement' => [
                    'soortEvenement' => ['Anders' => false, 'Festival' => true, 'Circus' => false],
                ],
            ]]],
        ], 200),
    ]);
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $values = $zaak->refresh()->form_state_snapshot['values'];

    // De geselecteerde key wordt één scalar — geen array.
    expect($values['soortEvenement'])->toBe('Festival')
        ->and($values['soortEvenement'])->toBeString();
});

test('single-select met alleen-false boolean-map wordt overgeslagen (geen array in snapshot)', function () {
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                'naam-van-het-evenement' => [
                    'soortEvenement' => ['Anders' => false, 'Festival' => false],
                ],
            ]]],
        ], 200),
    ]);
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $values = $zaak->refresh()->form_state_snapshot['values'];

    // Niets geselecteerd → key valt weg (geen lege array die fill() laat crashen).
    expect($values)->not->toHaveKey('soortEvenement');
});

test('FileUpload-velden (bijlagen) worden niet in de snapshot gezet — files komen uit OpenZaak', function () {
    // De OF-bijlage is een {url,name,size}-object naar een (dode) OF-
    // submission-URL. De echte files leven in OpenZaak's Documenten-API en
    // worden via Zaak::documenten gelezen. De backfill moet bijlagen1 (een
    // FileUpload-veld) daarom NIET overnemen.
    Http::fake([
        '*objects/*' => Http::response([
            'record' => ['data' => ['data' => [
                'bijlagen' => [
                    'bijlagen1' => [[
                        'url' => 'https://open-formulieren.test/api/v2/submissions/files/abc',
                        'name' => 'test-uuid.pdf',
                        'originalName' => 'test.pdf',
                        'size' => 6409,
                    ]],
                ],
                'contactgegevens' => ['soortEvenement' => 'festival'],
            ]]],
        ], 200),
    ]);
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $zaak->refresh();
    $values = $zaak->form_state_snapshot['values'];

    expect($values)->not->toHaveKey('bijlagen1')
        ->and($values)->toHaveKey('soortEvenement', 'festival');
});

test('--dry-run slaat niets op', function () {
    fakeObjectsRecord();
    $zaak = oudeZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id, '--dry-run' => true])
        ->assertSuccessful();

    $zaak->refresh();
    expect($zaak->form_state_snapshot)->toBeNull();
    Http::assertSent(fn ($request) => str_contains($request->url(), 'objects/abc-uuid-123'));
});

test('zaak met bestaande snapshot wordt overgeslagen (idempotent)', function () {
    $zaak = oudeZaak(['form_state_snapshot' => ['values' => ['al' => 'gevuld']]]);

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    // Geen Objects-call gedaan en de bestaande snapshot ongemoeid gelaten.
    Http::assertNothingSent();
    $zaak->refresh();
    expect($zaak->form_state_snapshot['values'])->toBe(['al' => 'gevuld']);
});

test('zaak zonder data_object_url valt buiten de selectie', function () {
    $zaak = oudeZaak(['data_object_url' => null]);

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    Http::assertNothingSent();
    $zaak->refresh();
    expect($zaak->form_state_snapshot)->toBeNull();
});
