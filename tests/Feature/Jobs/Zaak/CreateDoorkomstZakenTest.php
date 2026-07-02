<?php

use App\Enums\ZaaktypeRole;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

uses(RefreshDatabase::class);

const OWN_HOST = 'https://gemeente.example.com';

beforeEach(function () {
    if (config('database.default') === 'pgsql') {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        } catch (Exception $e) {
            // PostGIS is available in the Docker container.
        }
    }
});

/**
 * A diagonal route line y=x from (0.5,0.5) to (3.5,3.5):
 *  - start point (0.5,0.5) lies in the hoofdzaak municipality (excluded),
 *  - end point (3.5,3.5) lies in the end municipality (excluded),
 *  - the middle passes through the passing municipality (2,2).
 */
function routeSnapshot(): array
{
    return [
        'values' => [
            'routesOpKaart' => [
                'type' => 'LineString',
                'coordinates' => [[0.5, 0.5], [3.5, 3.5]],
            ],
        ],
    ];
}

function multipolygon(array $ring): string
{
    return json_encode(['type' => 'MultiPolygon', 'coordinates' => [[$ring]]]);
}

/**
 * The deelzaak read after creation, carrying the eigenschappen the local
 * ZaakReferenceData requires (start/eind evenement) plus a registratiedatum.
 */
function deelZaakReadResponse(): array
{
    return [
        'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1',
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'identificatie' => 'DEEL-1',
        'registratiedatum' => '2026-06-01',
        '_expand' => [
            'eigenschappen' => [
                ['naam' => 'start_evenement', 'waarde' => '2026-07-01 10:00'],
                ['naam' => 'eind_evenement', 'waarde' => '2026-07-01 18:00'],
            ],
        ],
    ];
}

/**
 * Fake the ZGW reads/writes the job performs. The deelzaak store returns a url so
 * the local Zaak is persisted; catalogi/relations degrade to empty lists.
 */
function fakeDoorkomstZgw(): void
{
    Http::fake([
        // Hoofdzaak read (own instance of the hoofdzaak municipality).
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),

        // Deelzaak store + read on the target connection (main = ZgwHttpFake base).
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },

        // Catalogi reads degrade to empty lists everywhere.
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

/**
 * Build the geospatial scenario and the hoofdzaak. The hoofdzaak municipality
 * (H) optionally runs its own ZGW instance; the passing municipality (M) gets a
 * Doorkomst zaaktype on the given connection.
 */
function doorkomstScenario(bool $hoofdOwnInstance): array
{
    $hoofd = Municipality::factory()->create([
        'name' => 'Hoofdgemeente',
        'geometry' => multipolygon([[0, 0], [0, 1], [1, 1], [1, 0], [0, 0]]),
    ]);
    if ($hoofdOwnInstance) {
        MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $hoofd->id]);
    }

    $passing = Municipality::factory()->create([
        'name' => 'Doorkomstgemeente',
        'geometry' => multipolygon([[1.5, 1.5], [1.5, 2.5], [2.5, 2.5], [2.5, 1.5], [1.5, 1.5]]),
    ]);

    Municipality::factory()->create([
        'name' => 'Eindgemeente',
        'geometry' => multipolygon([[3, 3], [3, 4], [4, 4], [4, 3], [3, 3]]),
    ]);

    $hoofdZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $hoofd->id,
        'role' => ZaaktypeRole::Vergunning,
        'triggers_route_check' => true,
        'is_active' => true,
        // The resolver routes by this column: an own-instance hoofdzaak carries
        // its own connection name, a main hoofdzaak stays on main.
        'connection' => $hoofdOwnInstance ? "gemeente_{$hoofd->id}" : 'main',
    ]);

    $hoofdzaak = Zaak::factory()->create([
        'zaaktype_id' => $hoofdZaaktype->id,
        'zgw_zaak_url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
        'form_state_snapshot' => routeSnapshot(),
    ]);

    return ['hoofd' => $hoofd, 'passing' => $passing, 'hoofdzaak' => $hoofdzaak];
}

test('creates a standalone zaak (no ZGW hoofdzaak) for a cross-instance doorkomst gemeente, linked locally', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);

    // The passing municipality uses the shared main connection (different instance
    // than the hoofdzaak's own instance), with a Doorkomst zaaktype on main.
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // A local doorkomst zaak is created, linked to the hoofdzaak via hoofdzaak_id.
    $deel = Zaak::where('zaaktype_id', $doorkomstZaaktype->id)->first();
    expect($deel)->not->toBeNull()
        ->and($deel->hoofdzaak_id)->toBe($scenario['hoofdzaak']->id);

    // The ZGW store POST did NOT include a cross-instance hoofdzaak reference.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken')
        && ! array_key_exists('hoofdzaak', $request->data()));
});

test('sets the ZGW hoofdzaak reference when the doorkomst gemeente shares the hoofdzaak instance', function () {
    // Hoofdzaak on main; passing gemeente's doorkomst zaaktype also on main, so
    // both live in the same instance and a real ZGW deelzaak link is possible.
    // A single Http::fake call so the hoofd-1 stub is matched before the broader
    // zaken* stub (a second fake() call would append, not replace).
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1']);

    Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken')
        && ($request->data()['hoofdzaak'] ?? null) === ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1');
});

/**
 * Fake a cross-instance doorkomst (own-instance hoofdzaak, main deelzaak). The
 * target roltypen expose an initiator roltype so the initiator is registered,
 * and the rollen POST is captured.
 */
function fakeDoorkomstForInitiator(): void
{
    Http::fake([
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen/init', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen*' => Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen/1'], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

function withPassingDoorkomstZaaktype(Municipality $passing): void
{
    Zaaktype::factory()->create([
        'municipality_id' => $passing->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);
}

/** A route snapshot plus the given aanvrager form values. */
function routeSnapshotWithValues(array $values): array
{
    return ['values' => array_merge(routeSnapshot()['values'], $values)];
}

test('registers the initiator on the deelzaak from the form aanvrager data, not the copied ZGW rol', function () {
    // The initiator is rebuilt from the form (KvK + organisation name), matching
    // the hoofdzaak. The hoofdzaak ZGW rol is not copied: its identificatie is
    // empty and its betrokkene url is not portable across instances.
    fakeDoorkomstForInitiator();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
        'watIsDeNaamVanUwOrganisatie' => 'Woweb',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen')
        && $request->data()['betrokkeneType'] === 'niet_natuurlijk_persoon'
        && $request->data()['roltype'] === ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen/init'
        && ($request->data()['betrokkeneIdentificatie']['kvkNummer'] ?? null) === '12345678'
        && ($request->data()['betrokkeneIdentificatie']['statutaireNaam'] ?? null) === 'Woweb');
});

test('registers a natuurlijk_persoon initiator from the form name when there is no KvK', function () {
    // A private aanvrager (no KvK). The name is not among the hashed snapshot
    // keys, and the builder sends no BSN, so a valid natuurlijk_persoon rol is
    // registered on the deelzaak.
    fakeDoorkomstForInitiator();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen')
        && $request->data()['betrokkeneType'] === 'natuurlijk_persoon'
        && ($request->data()['betrokkeneIdentificatie']['geslachtsnaam'] ?? null) === 'Jansen'
        && ($request->data()['betrokkeneIdentificatie']['voornamen'] ?? null) === 'Jan'
        && ! array_key_exists('kvkNummer', $request->data()['betrokkeneIdentificatie']));
});

test('skips the initiator when the form has no aanvrager data', function () {
    fakeDoorkomstForInitiator();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    // Only the route, no aanvrager fields → buildInitiator() is empty.
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen'));
});

test('does not create a doorkomst zaak when the passing gemeente has no doorkomst zaaktype', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);

    // No doorkomst zaaktype configured for the passing municipality.
    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(0);
});

test('is idempotent: running twice does not create a second doorkomst zaak', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);

    $doorkomstZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);
    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)
        ->where('zaaktype_id', $doorkomstZaaktype->id)
        ->count())->toBe(1);
});
