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

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\State\FormState;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
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

test('VOLLEDIGE KETEN: oude Objects-data -> command -> prefill van een nieuw formulier', function () {
    // Dit is waar het command voor bestaat: een aanvraag die als oude
    // submission in de Objects API zit, herbruikbaar maken in het nieuwe
    // formulier. Deze test draait de hele keten:
    //   oude OF-data -> backfill-command -> form_state_snapshot ->
    //   PrefillLoader -> FormState die het nieuwe formulier zou vullen.
    fakeFromFixture('poly_old');

    $org = Organisation::factory()->create();
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($org, ['role' => OrganisationRole::Admin->value]);

    $zaak = Zaak::factory()->create([
        'organisation_id' => $org->id,
        'organiser_user_id' => $user->id,
        'zaaktype_id' => Zaaktype::factory()->create()->id,
        'data_object_url' => 'https://objects.test/api/v2/objects/real-uuid',
        'form_state_snapshot' => null,
    ]);

    // 1. Command zet de oude Objects-submission om naar een snapshot.
    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    // 2. PrefillLoader bouwt — net als EventFormPage::mount() bij
    //    ?prefill_from_zaak — een FormState uit die snapshot.
    $state = (new PrefillLoader)->load($zaak->refresh()->id, $user, $org);

    // 3. Het nieuwe formulier krijgt de oude (gemapte) waarden binnen.
    expect($state)->toBeInstanceOf(FormState::class);
    expect($state->get('watIsDeNaamVanHetEvenementVergunning'))->not->toBeNull(); // legacy-key, nu gevuld
    expect($state->get('EvenementStart'))->not->toBeNull();
    expect($state->get('watIsUwVoornaam'))->not->toBeNull();
    // De kaart-tekening is als geojson FeatureCollection beschikbaar voor het Map-veld.
    expect($state->get('locatieSOpKaart.geojson.type'))->toBe('FeatureCollection');
});
