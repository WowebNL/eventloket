<?php

/**
 * "Herhaal aanvraag"-flow: een ingediende `Zaak` dient als vulling voor
 * een nieuw aanvraagformulier, zodat een organisator een vergelijkbaar
 * jaarlijks event niet helemaal opnieuw hoeft te typen.
 *
 * De flow in de UI is:
 *   1. ViewZaak-pagina → klik "Nieuwe aanvraag met deze gegevens"
 *   2. Redirect naar /organiser/{tenant}/aanvraag?prefill_from_zaak=<uuid>
 *   3. `EventFormDraftsPage::mount()` roept `PrefillLoader::load(<uuid>, user, org)`
 *   4. PrefillLoader levert een FormState met veldwaarden uit de bron-zaak;
 *      de overzichtspagina zet die in een níéuw concept en stuurt door naar
 *      het formulier (bestaande concepten blijven onaangetast)
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

test('gehashte waarden (hash:-prefix) worden gewist zodat applySessionPrefill ze kan overschrijven', function () {
    // HashIdentifyingAttributes vervangt KvK en BSN-velden door hash:<hmac>.
    // PrefillLoader moet die waarden strippen — anders staan er onleesbare
    // hashes in het formulier en wordt applySessionPrefill nooit getriggerd
    // (die slaat velden met een niet-lege waarde over).
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2026',
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => 'hash:1f7c6a828a8078a581b27c46379c66b78044b80b57f16d93b1a01a7d3c60128d',
        'bsn' => 'hash:aabbcc112233aabbcc112233aabbcc112233aabbcc112233aabbcc112233aabb',
        'auth_bsn' => 'hash:deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);

    expect($state)->toBeInstanceOf(FormState::class)
        // Niet-gevoelig veld blijft ongewijzigd
        ->and($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Buurtfeest 2026')
        // Gehashte velden zijn gewist (null of leeg), zodat applySessionPrefill kan invullen
        ->and($state->get('watIsHetKamerVanKoophandelNummerVanUwOrganisatie'))->toBeNull()
        ->and($state->get('bsn'))->toBeNull()
        ->and($state->get('auth_bsn'))->toBeNull();
});

test('locatie-afhankelijke state van de bron-zaak komt niet mee', function () {
    // De gemeentekeuze en de locatieserver-resultaten horen bij de locatie van
    // de bron-aanvraag. Zouden ze meekomen, dan wordt een kopie met een andere
    // locatie alsnog in de oorspronkelijke gemeente aangevraagd.
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'userSelectGemeente' => 'GM0917',
        'inGemeentenResponse' => ['all' => [
            'items' => [['brk_identification' => 'GM0917', 'name' => 'Heerlen']],
            'object' => ['GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen']],
        ]],
        'gemeenteVariabelen' => ['use_new_report_questions' => true],
        'evenementenInDeGemeente' => ['items' => []],
        'evenementInGemeente' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);

    expect($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Buurtfeest 2027')
        ->and($state->get('userSelectGemeente'))->toBeNull()
        ->and($state->get('inGemeentenResponse'))->toBeNull()
        ->and($state->get('gemeenteVariabelen'))->toBeNull()
        ->and($state->get('evenementenInDeGemeente'))->toBeNull()
        ->and($state->get('evenementInGemeente'))->toBeNull();
});

test('antwoorden op de aanvullende vragen van de bron-gemeente komen niet mee', function () {
    // De vragenlijst zelf verdwijnt met `gemeenteVariabelen`. Zouden de
    // antwoorden blijven staan, dan sleept een kopie ze mee naar een gemeente
    // met heel andere vragen.
    $sc = scenarioZaakMetSnapshot([
        'watIsDeNaamVanHetEvenementVergunning' => 'Buurtfeest 2027',
        'gemeenteVariabelen' => [
            'extra_questions' => [
                ['id' => 3, 'type' => 'text', 'label' => 'Nog iets?', 'options' => []],
            ],
        ],
        'extraVraag_3' => 'Ja, een springkussen',
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);

    expect($state->get('watIsDeNaamVanHetEvenementVergunning'))->toBe('Buurtfeest 2027')
        ->and($state->get('gemeenteVariabelen'))->toBeNull()
        ->and($state->get('extraVraag_3'))->toBeNull();
});

test('de brkGemeente van gekopieerde adresrijen wordt gewist', function () {
    // Het verborgen brkGemeente-veld wordt door de locatiecheck verbatim
    // vertrouwd; een gekopieerde waarde zou een gewijzigd adres naar de oude
    // gemeente routeren.
    //
    // De rijvorm is die van de repeater in LocatieVanHetEvenement2Step: rijen
    // gekeyed op uuid, met het adres genest onder de AddressNL-prefix. Dezelfde
    // vorm die ServiceFetcher::collectAddressesFromEditgrid() uitleest.
    $sc = scenarioZaakMetSnapshot([
        'adresVanDeGebouwEn' => [
            'row-1' => [
                'naamVanDeLocatieGebouw' => 'Hoofdgebouw',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6411CD',
                    'huisnummer' => '32',
                    'straatnaam' => 'Coriovallumstraat',
                    'brkGemeente' => 'GM0917',
                ],
            ],
            'row-2' => [
                'naamVanDeLocatieGebouw' => 'Bijgebouw',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6361BZ',
                    'huisnummer' => '1',
                    'straatnaam' => 'Deweverplein',
                    'brkGemeente' => 'GM1954',
                ],
            ],
        ],
    ]);

    $state = $this->loader->load($sc['zaak']->id, $sc['user'], $sc['org']);
    $adressen = $state->get('adresVanDeGebouwEn');

    expect($adressen)->toHaveCount(2)
        ->and($adressen)->toHaveKeys(['row-1', 'row-2'])
        ->and($adressen['row-1']['adresVanHetGebouwWaarUwEvenementPlaatsvindt1'])->not->toHaveKey('brkGemeente')
        ->and($adressen['row-2']['adresVanHetGebouwWaarUwEvenementPlaatsvindt1'])->not->toHaveKey('brkGemeente')
        // De ingevulde adresgegevens zelf blijven staan.
        ->and($adressen['row-1']['adresVanHetGebouwWaarUwEvenementPlaatsvindt1']['postcode'])->toBe('6411CD')
        ->and($adressen['row-1']['naamVanDeLocatieGebouw'])->toBe('Hoofdgebouw')
        ->and($adressen['row-2']['adresVanHetGebouwWaarUwEvenementPlaatsvindt1']['straatnaam'])->toBe('Deweverplein');
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
