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
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

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

    // CheckboxList: de OF-boolean-map {gebouw:true, buiten:false, route:false}
    // is omgezet naar Filament's array-shape ['gebouw']. Zonder dit rendert
    // het vinkje niet bij prefill en blijven afhankelijke secties verborgen.
    expect($values['waarVindtHetEvenementPlaats'])->toBe(['gebouw']);
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

test('REPRODUCTIE prod-crash: form->fill() van een gebackfillde zaak met soortEvenement-boolean-map crasht niet', function () {
    // Productie-error na de backfill: "Array to string conversion" in
    // OptionStateCast bij EventFormPage::mount() -> form->fill(). Oorzaak:
    // soortEvenement was een multi-checkbox (OF) en is nu een single Select;
    // de OF-boolean-map belandde ongecoerced in de snapshot. Deze test draait
    // de ECHTE keten — backfill -> mount -> fill — die de unit-tests misten
    // doordat het seed-endpoint handmatig de juiste shape zette.
    fakeFromFixture('soort_checkboxmap');

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

    $this->artisan('eventform:backfill-snapshots-from-objects', ['--zaak' => $zaak->id])
        ->assertSuccessful();

    // Geen array meer op een single-select-key in de snapshot.
    $values = $zaak->refresh()->form_state_snapshot['values'];
    expect($values['soortEvenement'] ?? null)->not->toBeArray();

    // De echte reproductie: EventFormPage mounten met ?prefill_from_zaak
    // triggert form->fill() met de snapshot. Vóór de fix knalde dit op
    // OptionStateCast; nu moet het schoon mounten.
    $this->actingAs($user);
    Filament::setTenant($org);

    Livewire::withQueryParams(['prefill_from_zaak' => $zaak->id])
        ->test(EventFormPage::class)
        ->assertOk();
});
