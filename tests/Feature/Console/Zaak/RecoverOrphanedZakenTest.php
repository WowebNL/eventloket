<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Jobs\Zaak\AddGeometryZGW;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['openzaak.objectsapi.url' => 'https://objects.test/']);
});

/**
 * Fakes the ZGW case GET and its Objects record, and returns the zaak url plus
 * the created organisation/user so tests can assert the linkage.
 *
 * @return array{url: string, organisation: Organisation, user: OrganiserUser, objectUuid: string}
 */
function fakeOrphanedZaak(array $overrides = []): array
{
    $zaaktypeUrl = $overrides['zaaktypeUrl'] ?? 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1';
    $zaakUrl = 'https://zgw.example.com/zaken/api/v1/zaken/orphan-1';
    $objectUuid = 'obj-orphan-1';
    $objectUrl = 'https://objects.test/api/v2/objects/'.$objectUuid;

    $organisation = Organisation::factory()->create(['uuid' => (string) Str::uuid()]);
    /** @var OrganiserUser $user */
    $user = User::factory()->state(['role' => Role::Organiser, 'uuid' => (string) Str::uuid()])->create();

    $prefix = strtolower((string) config('app.name'));

    $zaakResponse = [
        'uuid' => 'orphan-1',
        'url' => $zaakUrl,
        'identificatie' => 'ZAAK-ORPHAN-1',
        'zaaktype' => $zaaktypeUrl,
        'omschrijving' => 'Orphaned test zaak',
        'startdatum' => '2026-01-01',
        'registratiedatum' => '2026-01-01',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '123456789',
        'zaakgeometrie' => null,
        '_expand' => [
            'zaakobjecten' => [
                ['object' => $objectUrl, 'objectType' => 'overige', 'relatieomschrijving' => ''],
            ],
            'eigenschappen' => [
                eigenschap('start_evenement', '2026-06-01T10:00:00+02:00'),
                eigenschap('eind_evenement', '2026-06-01T20:00:00+02:00'),
                eigenschap('naam_evenement', 'Testfeest'),
            ],
            'rollen' => [
                [
                    'url' => 'https://zgw.example.com/zaken/api/v1/rollen/1',
                    'uuid' => 'rol-1',
                    'betrokkeneType' => 'natuurlijk_persoon',
                    'roltype' => 'https://zgw.example.com/catalogi/api/v1/roltypen/1',
                    'omschrijving' => 'Initiator',
                    'omschrijvingGeneriek' => 'initiator',
                    'contactpersoonRol' => [],
                    'betrokkeneIdentificatie' => ['voornamen' => 'Jan', 'geslachtsnaam' => 'Jansen'],
                ],
            ],
        ],
    ];

    $objectResponse = [
        'record' => [
            'data' => [
                "{$prefix}_organisation_uuid" => $organisation->uuid,
                "{$prefix}_user_uuid" => $user->uuid,
                'data' => ['watIsDeNaamVanHetEvenementVergunning' => 'Testfeest'],
            ],
        ],
    ];

    Http::fake([
        $zaakUrl.'*' => Http::response($zaakResponse, 200),
        $objectUrl.'*' => Http::response($objectResponse, 200),
        'https://objects.test/*' => Http::response($objectResponse, 200),
    ]);

    return ['url' => $zaakUrl, 'organisation' => $organisation, 'user' => $user, 'objectUuid' => $objectUuid];
}

function eigenschap(string $naam, string $waarde): array
{
    return [
        'uuid' => 'eig-'.Str::slug($naam),
        'url' => 'https://zgw.example.com/zaken/api/v1/zaakeigenschappen/'.Str::slug($naam),
        'zaak' => 'https://zgw.example.com/zaken/api/v1/zaken/orphan-1',
        'eigenschap' => 'https://zgw.example.com/catalogi/api/v1/eigenschappen/'.Str::slug($naam),
        'naam' => $naam,
        'waarde' => $waarde,
    ];
}

it('recreates the local zaak row linked to organisation, user and document url', function () {
    Queue::fake();
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']]])
        ->assertSuccessful();

    $zaak = Zaak::where('zgw_zaak_url', $fake['url'])->first();
    expect($zaak)->not->toBeNull();
    expect($zaak->public_id)->toBe('ZAAK-ORPHAN-1');
    expect($zaak->zaaktype_id)->toBe($zaaktype->id);
    expect($zaak->organisation_id)->toBe($fake['organisation']->id);
    expect($zaak->organiser_user_id)->toBe($fake['user']->id);
    expect($zaak->data_object_url)->toBe('https://objects.test/api/v2/objects/obj-orphan-1');
    expect($zaak->reference_data->organisator)->toBe('Jan Jansen');
});

it('is idempotent and does not duplicate an already recovered row', function () {
    Queue::fake();
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']]])->assertSuccessful();
    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']]])->assertSuccessful();

    expect(Zaak::where('zgw_zaak_url', $fake['url'])->count())->toBe(1);
});

it('writes nothing and dispatches nothing on --dry-run', function () {
    Queue::fake();
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']], '--dry-run' => true])
        ->assertSuccessful();

    expect(Zaak::where('zgw_zaak_url', $fake['url'])->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('dispatches AddGeometryZGW by default and skips it with --no-geometry', function () {
    Queue::fake();
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']]])->assertSuccessful();
    Queue::assertPushed(AddGeometryZGW::class);
});

it('does not dispatch AddGeometryZGW with --no-geometry', function () {
    Queue::fake();
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']], '--no-geometry' => true])
        ->assertSuccessful();

    expect(Zaak::where('zgw_zaak_url', $fake['url'])->exists())->toBeTrue();
    Queue::assertNotPushed(AddGeometryZGW::class);
});

it('discovers urls from failed UpdateInitiatorZGW jobs and recovers them', function () {
    Queue::fake();
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);
    $fake = fakeOrphanedZaak();

    $command = 'O:36:"App\\Jobs\\Zaak\\UpdateInitiatorZGW":1:{s:10:"zaakUrlZGW";s:50:"'.$fake['url'].'";}';
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'displayName' => 'App\\Jobs\\Zaak\\UpdateInitiatorZGW',
            'data' => ['command' => $command],
        ]),
        'exception' => 'ValidationError',
        'failed_at' => now(),
    ]);

    $this->artisan('zaak:recover-orphaned', ['--from-failed-jobs' => true])
        ->assertSuccessful();

    $zaak = Zaak::where('zgw_zaak_url', $fake['url'])->first();
    expect($zaak)->not->toBeNull();
    expect($zaak->zaaktype_id)->toBe($zaaktype->id);
});

it('skips a case whose zaaktype is missing or inactive without creating a row', function () {
    Queue::fake();
    // No matching active zaaktype created.
    $fake = fakeOrphanedZaak();

    $this->artisan('zaak:recover-orphaned', ['url' => [$fake['url']]])
        ->assertSuccessful();

    expect(Zaak::where('zgw_zaak_url', $fake['url'])->exists())->toBeFalse();
});

it('still creates the row with null org and user when the Objects API lookup fails', function () {
    Queue::fake();
    $zaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'is_active' => true,
    ]);

    $zaakUrl = 'https://zgw.example.com/zaken/api/v1/zaken/orphan-1';
    $zaakResponse = [
        'uuid' => 'orphan-1',
        'url' => $zaakUrl,
        'identificatie' => 'ZAAK-ORPHAN-1',
        'zaaktype' => 'https://zgw.example.com/catalogi/api/v1/zaaktypen/1',
        'omschrijving' => 'Orphaned test zaak',
        'startdatum' => '2026-01-01',
        'registratiedatum' => '2026-01-01',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '123456789',
        'zaakgeometrie' => null,
        '_expand' => [
            'zaakobjecten' => [
                ['object' => 'https://objects.test/api/v2/objects/obj-orphan-1', 'objectType' => 'overige', 'relatieomschrijving' => ''],
            ],
            'eigenschappen' => [
                eigenschap('start_evenement', '2026-06-01T10:00:00+02:00'),
                eigenschap('eind_evenement', '2026-06-01T20:00:00+02:00'),
            ],
        ],
    ];

    Http::fake([
        $zaakUrl.'*' => Http::response($zaakResponse, 200),
        'https://objects.test/*' => Http::response([], 500),
    ]);

    $this->artisan('zaak:recover-orphaned', ['url' => [$zaakUrl], '--no-geometry' => true])
        ->assertSuccessful();

    $zaak = Zaak::where('zgw_zaak_url', $zaakUrl)->first();
    expect($zaak)->not->toBeNull();
    expect($zaak->organisation_id)->toBeNull();
    expect($zaak->organiser_user_id)->toBeNull();
    expect($zaak->zaaktype_id)->toBe($zaaktype->id);
});
