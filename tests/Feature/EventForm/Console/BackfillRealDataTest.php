<?php

declare(strict_types=1);

/**
 * End-to-end test van de Objects-backfill tegen ÉCHTE submission-vormen.
 *
 * De fixtures in tests/Fixtures/objects_api/ zijn echte staging-records
 * (PII gescrubd, geometrie/structuur/keys intact). Dit bewijst dat de
 * extractie/mapping/transform-keten werkt op de productie-vormen — niet
 * alleen op hand-gemaakte fixtures.
 */

use App\Models\Organisation;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

function fakeFromFixture(string $name): void
{
    $json = json_decode((string) file_get_contents(base_path("tests/Fixtures/objects_api/{$name}.json")), true);
    Http::fake(['*objects/*' => Http::response($json, 200)]);
}

function backfillZaak(): Zaak
{
    return Zaak::factory()->create([
        'organisation_id' => Organisation::factory()->create()->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'data_object_url' => 'https://objects.test/api/v2/objects/real-uuid',
        'form_state_snapshot' => null,
    ]);
}

test('echte oude-generatie submission met polygon: legacy-mapping + kaart-transform', function () {
    fakeFromFixture('poly_old');
    $zaak = backfillZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $values = $zaak->refresh()->form_state_snapshot['values'];

    // Legacy-keys uit de oude OF-generatie zijn omgezet naar de nieuwe form-keys.
    expect(array_keys($values))->toContain(
        'watIsDeNaamVanHetEvenementVergunning',          // was watIsDeNaamVanHetEvenement
        'EvenementStart',                                // was watIsDeStarttijdVanHetEvenement
        'EvenementEind',                                 // was wanneerIsHetEindeVanHetEvenement
        'watIsUwVoornaam',                               // was voornaamIngelogdePersoon
        'watIsUwAchternaam',                             // was achternaamIngelogdePersoon
        'watIsHetAantalGelijktijdigAanwezigPersonen',    // was aantalGelijktijdigAanwezigePersonen
    );

    // De oude legacy-keys zelf staan NIET in de snapshot.
    expect(array_keys($values))->not->toContain('watIsDeNaamVanHetEvenement');
    expect(array_keys($values))->not->toContain('voornaamIngelogdePersoon');

    // De polygon-tekening is omgezet naar de geojson-shape die het Map-veld leest.
    $geojson = $values['locatieSOpKaart']['geojson'];
    expect($geojson['type'])->toBe('FeatureCollection');
    expect($geojson['features'][0]['geometry']['type'])->toBe('Polygon');
    // De echte coördinaten zijn intact gebleven.
    expect($geojson['features'][0]['geometry']['coordinates'])->not->toBeEmpty();
});

test('echte submission met bijlage: OF-file-object belandt niet in de snapshot', function () {
    fakeFromFixture('bijlage');
    $zaak = backfillZaak();

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    $values = $zaak->refresh()->form_state_snapshot['values'];

    // bijlagen1 (FileUpload) wordt niet overgenomen — files komen uit OpenZaak.
    expect(array_keys($values))->not->toContain('bijlagen1');
    // Maar de rest van de submission is wél verwerkt.
    expect(array_keys($values))->toContain('watIsDeNaamVanHetEvenementVergunning');
});
