<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\Enums\Role;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->fetcher = app(ServiceFetcher::class);
});

describe('ServiceFetcher eventloketSession', function () {
    test('fills eventloketSession with user + organisation data', function () {
        $user = User::factory()->create([
            'role' => Role::Organiser,
            'first_name' => 'Eva',
            'last_name' => 'Janssen',
            'email' => 'eva@example.nl',
            'phone' => '0612345678',
        ]);
        $organisation = Organisation::factory()->create([
            'name' => 'Evenementen BV',
            'coc_number' => '12345678',
        ]);

        $state = FormState::empty();
        $state->setSystem('authUser', $user);
        $state->setSystem('authOrganisation', $organisation);

        $this->fetcher->fetch('eventloketSession', $state);

        expect($state->get('eventloketSession.user_first_name'))->toBe('Eva')
            ->and($state->get('eventloketSession.organisation_name'))->toBe('Evenementen BV')
            ->and($state->get('eventloketSession.kvk'))->toBe('12345678');
    });

    test('no-op when authUser or authOrganisation is missing', function () {
        $state = FormState::empty();

        $this->fetcher->fetch('eventloketSession', $state);

        expect($state->get('eventloketSession'))->toBeNull();
    });
});

describe('ServiceFetcher gemeenteVariabelen', function () {
    test('fetches variables for the municipality stored in evenementInGemeente.brk_identification', function () {
        $municipality = Municipality::factory()->create(['brk_identification' => 'GM0882']);
        // Observer seedt defaults — wis ze zodat we exacte asserties
        // over de fixture-data kunnen doen.
        $municipality->variables()->forceDelete();
        MunicipalityVariable::factory()->create([
            'municipality_id' => $municipality->id,
            'key' => 'aanwezigen',
            'type' => MunicipalityVariableType::Number,
            'value' => 500,
        ]);
        MunicipalityVariable::factory()->create([
            'municipality_id' => $municipality->id,
            'key' => 'melding_maximale_dba',
            'type' => MunicipalityVariableType::Number,
            'value' => 85,
        ]);

        $state = FormState::empty();
        $state->setVariable('evenementInGemeente', ['brk_identification' => 'GM0882']);

        $this->fetcher->fetch('gemeenteVariabelen', $state);

        expect($state->get('gemeenteVariabelen.aanwezigen'))->toBe(500.0)
            ->and($state->get('gemeenteVariabelen.melding_maximale_dba'))->toBe(85.0);
    });

    test('no-op when no brk_identification is present in state', function () {
        $state = FormState::empty();

        $this->fetcher->fetch('gemeenteVariabelen', $state);

        expect($state->get('gemeenteVariabelen'))->toBeNull();
    });
});

describe('ServiceFetcher evenementenInDeGemeente', function () {
    test('fetches events list for the given municipality and date range', function () {
        $municipality = Municipality::factory()->create(['brk_identification' => 'GM0882']);
        $zaaktype = Zaaktype::factory()->create(['municipality_id' => $municipality->id]);
        Zaak::factory()->create([
            'zaaktype_id' => $zaaktype->id,
            'reference_data' => new ZaakReferenceData(
                start_evenement: '2026-06-15',
                eind_evenement: '2026-06-16',
                registratiedatum: now()->toIso8601String(),
                status_name: 'Nieuw',
                statustype_url: '',
                naam_evenement: 'Zomerfestival',
            ),
        ]);

        $state = FormState::empty();
        $state->setField('EvenementStart', '2026-06-01T09:00:00');
        $state->setField('EvenementEind', '2026-06-30T18:00:00');
        $state->setVariable('evenementInGemeente', ['brk_identification' => 'GM0882']);

        $this->fetcher->fetch('evenementenInDeGemeente', $state);

        expect($state->get('evenementenInDeGemeente'))->toBe('Zomerfestival');
    });
});

describe('ServiceFetcher inGemeentenResponse', function () {
    test('fetches location-server response for addresses from editgrid', function () {
        Municipality::factory()->create(['brk_identification' => 'GM0882', 'name' => 'Maastricht']);
        Http::fake([
            'api.pdok.nl/bzk/locatieserver/search/v3_1/free*' => Http::response([
                'response' => ['docs' => [['gemeentecode' => '0882', 'gemeentenaam' => 'Maastricht']]],
            ]),
        ]);

        $state = FormState::empty();
        $state->setField('adresVanDeGebouwEn', [
            [
                'naamVanDeLocatieGebouw' => 'Stadhuis',
                'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
                    'postcode' => '6211AA', 'huisnummer' => '1',
                ],
            ],
        ]);

        $this->fetcher->fetch('inGemeentenResponse', $state);

        $result = $state->get('inGemeentenResponse');
        expect($result)->toBeArray()
            ->and($result['all']['items'])->toHaveCount(1);
    });
});

describe('ServiceFetcher unknown variable', function () {
    test('fetch() is a no-op for variables without a configured service', function () {
        $state = FormState::empty();

        $this->fetcher->fetch('something-else-that-does-not-exist', $state);

        expect($state->get('something-else-that-does-not-exist'))->toBeNull();
    });
});
