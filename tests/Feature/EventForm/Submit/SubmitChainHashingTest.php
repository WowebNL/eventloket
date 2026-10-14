<?php

/**
 * The submit chain hashes the identifying values (KvK, BSN) in its last job.
 * A chain stops at the first job that fails for good, so without a fallback a
 * failing job earlier in the chain leaves those values in plain form in the
 * stored snapshot for good.
 *
 * These tests take the chain exactly as SubmitEventForm builds it and run the
 * doorkomst job from it through the sync queue, the same failure path a queue
 * worker takes once a job has used up its attempts. The ZGW API is faked; no
 * request leaves the test.
 */

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\EventForm\State\FormState;
use App\EventForm\Submit\SubmitEventForm;
use App\Jobs\Submit\GenerateSubmissionPdf;
use App\Jobs\Submit\HashIdentifyingAttributes;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Testing\Fakes\BusFake;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (config('database.default') === 'pgsql') {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        } catch (Exception $e) {
            // PostGIS is available in the Docker container.
        }
    }
});

function chainHashingSquare(float $from, float $to): string
{
    return json_encode(['type' => 'MultiPolygon', 'coordinates' => [[[
        [$from, $from], [$from, $to], [$to, $to], [$to, $from], [$from, $from],
    ]]]]);
}

/**
 * A route event submitted by an organisation with a KvK number, whose route
 * starts in one municipality, passes a second one and ends outside both, so
 * the doorkomst job has work to do.
 *
 * @return array{state: FormState, user: OrganiserUser, organisation: Organisation}
 */
function chainHashingRouteScenario(): array
{
    Municipality::factory()->create([
        'name' => 'Startgemeente',
        'brk_identification' => 'GM9901',
        'geometry' => chainHashingSquare(0, 1),
    ]);
    Municipality::factory()->create([
        'name' => 'Doorkomstgemeente',
        'brk_identification' => 'GM9902',
        'geometry' => chainHashingSquare(1.5, 2.5),
    ]);

    Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning Startgemeente',
        'municipality_id' => Municipality::where('brk_identification', 'GM9901')->value('id'),
        'is_active' => true,
        'triggers_route_check' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
    ]);

    $organisation = Organisation::factory()->create(['name' => 'Voorbeeld Organisatie']);
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser])->create();
    $user->organisations()->attach($organisation, ['role' => OrganisationRole::Admin->value]);

    $state = new FormState(values: [
        'evenementInGemeente' => ['brk_identification' => 'GM9901', 'name' => 'Startgemeente'],
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        'watIsDeNaamVanHetEvenementVergunning' => 'Voorbeeldloop',
        'soortEvenement' => 'Sportevenement',
        'EvenementStart' => '2026-06-14T14:00',
        'EvenementEind' => '2026-06-14T18:00',
        'aantalVerwachteAanwezigen' => 80,
        'risicoClassificatie' => 'A',
        'routesOpKaart' => [
            'type' => 'LineString',
            'coordinates' => [[0.5, 0.5], [3.5, 3.5]],
        ],
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'bsn' => '123456789',
    ]);

    return compact('state', 'user', 'organisation');
}

function fakeChainHashingZaakCreate(): void
{
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    Http::preventStrayRequests();
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1',
            'uuid' => 'new-1',
            'identificatie' => 'ZAAK-2026-0001',
            'bronorganisatie' => '820151130',
            'startdatum' => now()->toDateString(),
            'registratiedatum' => now()->toDateString(),
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1',
            'omschrijving' => 'Voorbeeldloop',
        ], 201),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1', 'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/1', 'omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
        ]), 200),
        // Everything the doorkomst job reads from the hoofdzaak fails for good.
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/new-1*' => Http::response(['detail' => 'A server error occurred.'], 500),
        '*' => Http::response([], 200),
    ]);
}

/**
 * Submit, capture the chain SubmitEventForm dispatches, and return the doorkomst
 * job from it, prepared the way the queue hands it over when every job before
 * it has succeeded: carrying the rest of the chain and the chain's catch
 * callbacks. The real dispatcher is restored so the job then runs for real.
 */
function submitAndTakeDoorkomstJobFromChain(array $sc): CreateDoorkomstZaken
{
    /** @var BusFake $bus */
    $bus = Bus::fake();

    app(SubmitEventForm::class)->execute($sc['state'], $sc['user'], $sc['organisation']);

    $first = $bus->dispatched(GenerateSubmissionPdf::class)->sole();

    Bus::swap($bus->dispatcher);

    $chained = $first->chained;
    do {
        $job = unserialize(array_shift($chained));
    } while (! $job instanceof CreateDoorkomstZaken);

    $job->chained = $chained;
    $job->chainCatchCallbacks = $first->chainCatchCallbacks;

    return $job;
}

test('the identifying values are hashed when the doorkomst job in the submit chain fails for good', function () {
    $sc = chainHashingRouteScenario();
    fakeChainHashingZaakCreate();

    $job = submitAndTakeDoorkomstJobFromChain($sc);

    try {
        dispatch($job);
        $this->fail('Expected the doorkomst job to fail.');
    } catch (ApiRequestException) {
        // The failure itself still surfaces.
    }

    $values = Zaak::sole()->form_state_snapshot['values'];

    expect($values['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'])->toStartWith(HashIdentifyingAttributes::HASH_PREFIX)
        ->and($values['bsn'])->toStartWith(HashIdentifyingAttributes::HASH_PREFIX)
        ->and($values['watIsDeNaamVanHetEvenementVergunning'])->toBe('Voorbeeldloop');
});

test('keeps the hash job as the last link of the chain and does not hash up front', function () {
    $sc = chainHashingRouteScenario();
    fakeChainHashingZaakCreate();
    Bus::fake();

    app(SubmitEventForm::class)->execute($sc['state'], $sc['user'], $sc['organisation']);

    $first = Bus::dispatched(GenerateSubmissionPdf::class)->sole();

    // The hash job is still the last link, and nothing hashes before the
    // chain has run: the catch callback only fires on a failure.
    expect(get_class(unserialize(end($first->chained))))->toBe(HashIdentifyingAttributes::class)
        ->and($first->chainCatchCallbacks)->toHaveCount(1);
    Bus::assertNotDispatched(HashIdentifyingAttributes::class);
    expect(Zaak::sole()->form_state_snapshot['values']['watIsHetKamerVanKoophandelNummerVanUwOrganisatie'])->toBe('12345678');
});
