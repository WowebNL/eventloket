<?php

/**
 * "Herhaal aanvraag"-flow: een ingediende `Zaak` dient als vulling voor
 * een nieuw aanvraagformulier, zodat een organisator een vergelijkbaar
 * jaarlijks event niet helemaal opnieuw hoeft te typen.
 *
 * De flow in de UI is:
 *   1. ViewZaak-pagina → klik "Nieuwe aanvraag met deze gegevens"
 *   2. Redirect naar /organiser/{tenant}/aanvraag?prefill_from_zaak=<uuid>
 *   3. `EventFormPage::mount()` roept `PrefillLoader::load(<uuid>, user, org)`
 *   4. PrefillLoader levert een FormState met veldwaarden uit de bron-zaak
 *
 * Deze tests dekken:
 *   - Happy-path: snapshot komt uit de Zaak terug in FormState (rijkste bron)
 *   - Fallback: als er géén snapshot is, worden reference_data-velden gemapt
 *   - Veiligheid: een Zaak uit een andere organisatie geeft `null`, geen prefill
 *   - Robuust: missende of onbekende velden in de snapshot crashen niet —
 *     de rest van de state komt er gewoon doorheen (formulier kan tussentijds
 *     van schema veranderen).
 *   - Onbekend UUID / lege query-param → `null` (geen prefill).
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\Persistence\PrefillLoader;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loader = new PrefillLoader;
});

function zaaktypeMetMunicipality(): Zaaktype
{
    $muni = Municipality::factory()->create();

    return Zaaktype::factory()->create([
        'municipality_id' => $muni->id,
        'is_active' => true,
    ]);
}

function scenarioZaakMetSnapshot(array $values, ?Organisation $org = null): array
{
    $org ??= Organisation::factory()->create();
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($org, ['role' => OrganisationRole::Admin->value]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => zaaktypeMetMunicipality()->id,
        'organisation_id' => $org->id,
        'organiser_user_id' => $user->id,
        'form_state_snapshot' => [
            'values' => $values,
            'system' => [],
            'field_hidden' => ['someVisibleField' => true], // wordt gestripped
            'step_applicable' => ['someStep' => false],     // wordt gestripped
        ],
    ]);

    return compact('zaak', 'user', 'org');
}

test('laadt FormState uit form_state_snapshot als die aanwezig is', function () {
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'soortEvenement' => 'Buurtfeest',
        'EvenementStart' => '2027-06-14T14:00',
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);

    expect($state)->toBeInstanceOf(FormState::class)
        ->and($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Buurtfeest 2027')
        ->and($state->get('soortEvenement'))->toBe('Buurtfeest')
        ->and($state->get('EvenementStart'))->toBe('2027-06-14T14:00');
});

test('afgeleide state (field_hidden + step_applicable) uit vorige submit wordt niet meegeprefill', function () {
    // Als we "vorige" rules-uitkomsten meenemen, ziet een nieuwe aanvraag
    // er raar uit (velden verborgen die nu weer zichtbaar moeten zijn).
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);
    $snap = $state->toSnapshot();

    expect($snap['field_hidden'])->toBe([])
        ->and($snap['step_applicable'])->toBe([]);
});

test('zaak zonder snapshot → fallback naar reference_data-mapping', function () {
    $org = Organisation::factory()->create();
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($org, ['role' => OrganisationRole::Admin->value]);

    $zaak = Zaak::factory()->create([
        'zaaktype_id' => zaaktypeMetMunicipality()->id,
        'organisation_id' => $org->id,
        'organiser_user_id' => $user->id,
        'form_state_snapshot' => null,
        'reference_data' => new ZaakReferenceData(
            start_evenement: '2026-06-14T14:00:00+02:00',
            eind_evenement: '2026-06-14T18:00:00+02:00',
            registratiedatum: now()->toIso8601String(),
            status_name: 'Ingediend',
            statustype_url: '',
            naam_evenement: 'Oude Buurtfeest',
            aanwezigen: '50',
            types_evenement: 'Buurtfeest',
        ),
    ]);

    $state = $this->loader->load($zaak->id, $user, $org);

    expect($state)->toBeInstanceOf(FormState::class)
        ->and($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Oude Buurtfeest')
        ->and($state->get('watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX'))->toBe('50');
    // `soortEvenement` wordt in reference_data genormaliseerd naar
    // `types_evenement_array` — alleen als het oorspronkelijk als
    // JSON-array was opgeslagen komt die informatie terug. Voor deze
    // fallback-test is de naam-only property leidend en is dat genoeg.
});

test('zaak uit een andere organisatie levert geen prefill op (cross-tenant veilig)', function () {
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Andermans feest',
    ]);

    // Een andere user met een eigen organisatie probeert de UUID te misbruiken.
    $anderOrg = Organisation::factory()->create();
    /** @var OrganiserUser $anderUser */
    $anderUser = User::factory()->state(['role' => Role::Organiser])->create();
    $anderUser->organisations()->attach($anderOrg, ['role' => OrganisationRole::Admin->value]);

    $state = $this->loader->load($sc['zaak']->id, $anderUser, $anderOrg);

    expect($state)->toBeNull();
});

test('onbekend UUID → null, geen exception', function () {
    $org = Organisation::factory()->create();
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($org, ['role' => OrganisationRole::Admin->value]);

    $state = $this->loader->load('00000000-0000-0000-0000-000000000000', $user, $org);

    expect($state)->toBeNull();
});

test('lege of null query-param → null (geen prefill-actie)', function () {
    $org = Organisation::factory()->create();
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($org, ['role' => OrganisationRole::Admin->value]);

    expect($this->loader->load(null, $user, $org))->toBeNull();
    expect($this->loader->load('', $user, $org))->toBeNull();
});

test('velden die niet meer in het schema zitten komen stil mee uit de snapshot', function () {
    // Voorbeeld: een veld dat bij de vorige submit bestond maar inmiddels
    // vervangen is door een andere key. Dat mag niet crashen; de waarde
    // komt gewoon in de state maar geen enkele huidige stap gebruikt 'm.
    // De "stille"-garantie is dat FormState::get() een onbekende sleutel
    // gewoon teruggeeft zonder foutmeldingen.
    $sc = scenarioZaakMetSnapshot([
        'dezeVeldKeyBestaatNietMeerInHetSchema' => 'oude waarde',
        'watIsDeNaamVanHetEvenementVergunning' => 'Huidige veldkey',
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);

    expect($state)->toBeInstanceOf(FormState::class)
        ->and($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Huidige veldkey')
        ->and($state->get('dezeVeldKeyBestaatNietMeerInHetSchema'))->toBe('oude waarde'); // komt mee, stil
});
