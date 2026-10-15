<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Exceptions\DoorkomstDeelzaakIncompleteException;
use App\Exceptions\DoorkomstZaakeigenschapRejectedException;
use App\Exceptions\DoorkomstZakenIncompleteException;
use App\Exceptions\ZaaktypeConnectionMismatchException;
use App\Jobs\Zaak\CreateConceptAdviceQuestions;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\MunicipalityZgwConnection;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Zgw\Exceptions\ApiRequestException;

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
 * The deelzaak read after creation, carrying the evenement eigenschappen plus a
 * registratiedatum. Pass an empty list to simulate a doorkomst zaaktype whose
 * catalogus does not know those eigenschappen at all.
 *
 * @param  list<array<string, string>>|null  $eigenschappen
 */
function deelZaakReadResponse(?array $eigenschappen = null): array
{
    return [
        'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1',
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'identificatie' => 'DEEL-1',
        'registratiedatum' => '2026-06-01',
        '_expand' => [
            'eigenschappen' => $eigenschappen ?? [
                ['naam' => 'start_evenement', 'waarde' => '2026-07-01 10:00'],
                ['naam' => 'eind_evenement', 'waarde' => '2026-07-01 18:00'],
            ],
        ],
    ];
}

/**
 * Fake the ZGW reads/writes the job performs. The deelzaak store returns a url so
 * the local Zaak is persisted; catalogi/relations degrade to empty lists.
 *
 * @param  list<array<string, string>>|null  $deelZaakEigenschappen  null keeps the default evenement eigenschappen
 */
function fakeDoorkomstZgw(?array $deelZaakEigenschappen = null): void
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
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use ($deelZaakEigenschappen) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse($deelZaakEigenschappen), 200);
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

/**
 * Fake a doorkomst whose deelzaak lands on the passing municipality's own
 * instance (OWN_HOST) while the hoofdzaak lives on main, so the initiator rol is
 * posted to a non-default connection.
 */
function fakeDoorkomstForInitiatorOnOwnInstance(): void
{
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        OWN_HOST.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => OWN_HOST.'/catalogi/api/v1/roltypen/init', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        OWN_HOST.'/zaken/api/v1/rollen*' => Http::response(['url' => OWN_HOST.'/zaken/api/v1/rollen/1'], 201),
        OWN_HOST.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => OWN_HOST.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(array_merge(deelZaakReadResponse(), [
                'url' => OWN_HOST.'/zaken/api/v1/zaken/deel-1',
                'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/dk-m',
            ]), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

test('registers a vestiging initiator on a deelzaak in the doorkomst gemeente own instance', function () {
    // Non-default connection: the KvK number goes out as a vestiging rol, the
    // only betrokkeneType in the Zaken API that defines a kvkNummer property.
    // annIdentificatie and statutaireNaam belong to niet_natuurlijk_persoon and
    // are not part of RolVestiging, so neither is sent.
    fakeDoorkomstForInitiatorOnOwnInstance();

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update([
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
        'form_state_snapshot' => routeSnapshotWithValues([
            'watIsHetKamerVanKoophandelNummerVanUwOrganisatie' => '12345678',
            'watIsDeNaamVanUwOrganisatie' => 'Woweb',
        ]),
    ]);

    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $scenario['passing']->id]);
    Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$scenario['passing']->id}",
        'zgw_zaaktype_url' => OWN_HOST.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(function ($request) {
        if ($request->method() !== 'POST' || ! str_starts_with($request->url(), OWN_HOST.'/zaken/api/v1/rollen')) {
            return false;
        }

        $identificatie = $request->data()['betrokkeneIdentificatie'] ?? [];

        return $request->data()['betrokkeneType'] === 'vestiging'
            && $request->data()['roltype'] === OWN_HOST.'/catalogi/api/v1/roltypen/init'
            && $request->data()['roltoelichting'] === 'inzender formulier'
            && ($identificatie['kvkNummer'] ?? null) === '12345678'
            && ($identificatie['handelsnaam'] ?? null) === ['Woweb']
            && ! array_key_exists('annIdentificatie', $identificatie)
            && ! array_key_exists('statutaireNaam', $identificatie);
    });
});

test('creates a deelzaak on another connection as the organisation of that connection', function () {
    // Hoofdzaak on main, deelzaak on the passing municipality's own connection
    // with its own RSIN: the deelzaak must not carry the hoofdzaak's RSIN.
    fakeDoorkomstForInitiatorOnOwnInstance();
    config(['zgw.connections.main.bronorganisatie_rsin' => '123456789']);

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1']);

    MunicipalityZgwConnection::factory()->active()->create([
        'municipality_id' => $scenario['passing']->id,
        'bronorganisatie_rsin' => '987654321',
    ]);
    Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$scenario['passing']->id}",
        'zgw_zaaktype_url' => OWN_HOST.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), OWN_HOST.'/zaken/api/v1/zaken')
        && ($request->data()['bronorganisatie'] ?? null) === '987654321'
        && ($request->data()['verantwoordelijkeOrganisatie'] ?? null) === '987654321');
});

test('creates a deelzaak on the hoofdzaak connection with the RSIN the hoofdzaak carries', function () {
    // Same connection for both: the connection RSIN equals the hoofdzaak's
    // bronorganisatie, so the payload is what it was before.
    config(['zgw.connections.main.bronorganisatie_rsin' => '123456789']);
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
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
        && ($request->data()['bronorganisatie'] ?? null) === '123456789'
        && ($request->data()['verantwoordelijkeOrganisatie'] ?? null) === '123456789');
});

test('registers the initiator on the deelzaak from the form aanvrager data, not the copied ZGW rol', function () {
    // The initiator is rebuilt from the form (KvK + organisation name), matching
    // the hoofdzaak. The hoofdzaak ZGW rol is not copied: its identificatie is
    // empty and its betrokkene url is not portable across instances. The
    // deelzaak lands on our own default connection here, which keeps the
    // niet_natuurlijk_persoon payload it has always received.
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
        && ($request->data()['betrokkeneIdentificatie']['annIdentificatie'] ?? null) === '12345678'
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
        && ! array_key_exists('kvkNummer', $request->data()['betrokkeneIdentificatie'])
        && ! array_key_exists('annIdentificatie', $request->data()['betrokkeneIdentificatie']));
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

test('takes the organisator from the aanvraag, not from the (empty) ZGW rol of the hoofdzaak', function () {
    // The hoofdzaak read returns no rollen at all, which is what an instance
    // that does not expose betrokkeneIdentificatie (OneGround/RX Mission) comes
    // down to. The deelzaak must still show the organisator of the aanvraag.
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    $referenceData = $scenario['hoofdzaak']->reference_data;
    $scenario['hoofdzaak']->update([
        'reference_data' => new ZaakReferenceData(
            ...array_merge($referenceData->toArray(), ['organisator' => 'Stichting Zomerfeest'])
        ),
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();
    expect($deel->reference_data->organisator)->toBe('Stichting Zomerfeest');
});

test('falls back to the organisation of the hoofdzaak when its reference data has no organisator', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    // The factory leaves organisator empty (older zaken predate the field).
    $organisation = Organisation::factory()->create(['name' => 'Woweb']);
    $scenario['hoofdzaak']->update(['organisation_id' => $organisation->id]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();
    expect($deel->reference_data->organisator)->toBe('Woweb');
});

test('creates the deelzaak without any eigenschap, taking the evenement dates from the hoofdzaak', function () {
    // A doorkomst zaaktype whose catalogus knows none of the evenement
    // eigenschappen: the deelzaak read comes back empty. The job used to die on
    // the missing start_evenement/eind_evenement constructor arguments.
    fakeDoorkomstZgw(deelZaakEigenschappen: []);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();
    $hoofd = $scenario['hoofdzaak']->reference_data;

    expect($deel->public_id)->toBe('DEEL-1')
        ->and($deel->reference_data->start_evenement)->toBe($hoofd->start_evenement)
        ->and($deel->reference_data->eind_evenement)->toBe($hoofd->eind_evenement)
        ->and($deel->reference_data->start_evenement_datetime)->not->toBeNull();
});

test('registers the deelzaak locally when neither the deelzaak nor the hoofdzaak has evenement dates', function () {
    fakeDoorkomstZgw(deelZaakEigenschappen: []);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    $scenario['hoofdzaak']->update([
        'reference_data' => new ZaakReferenceData(
            ...array_merge($scenario['hoofdzaak']->reference_data->toArray(), [
                'start_evenement' => null,
                'eind_evenement' => null,
            ])
        ),
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->start_evenement)->toBeNull()
        ->and($deel->reference_data->eind_evenement)->toBeNull()
        ->and($deel->reference_data->start_evenement_datetime)->toBeNull();
});

test('stores exactly the same reference_data as before when the deelzaak carries its own eigenschappen', function () {
    // Regression anchor for the fallback: for the eigenschappen the deelzaak
    // does carry (the two dates) the fallback must not fire, so those keep the
    // deelzaak's own values, and the shape of the whole row stays what it was,
    // key order included. (The jsonb column itself does not preserve key order,
    // so the assertion runs on the emitted array.) The event name is the one
    // key this deelzaak read does not carry, so it comes from the hoofdzaak.
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->toArray())->toBe([
        'risico_classificatie' => null,
        'risico_toelichting' => null,
        'start_evenement' => '2026-07-01 10:00',
        'eind_evenement' => '2026-07-01 18:00',
        'registratiedatum' => '2026-06-01',
        'status_name' => '',
        'statustype_url' => '',
        'naam_evenement' => 'Test event',
        'naam_locatie_evenement' => null,
        'organisator' => '',
        'resultaat' => null,
        'resultaattype_url' => null,
        'aanwezigen' => null,
        'types_evenement' => null,
        'start_opbouw' => null,
        'eind_opbouw' => null,
        'start_afbouw' => null,
        'eind_afbouw' => null,
        'locaties_evenement' => null,
        'intern_zaaknummer' => null,
        'dagen_evenement' => null,
        'dagen_opbouw' => null,
        'dagen_afbouw' => null,
    ]);
});

test('stores the deelzaak evenement dates as ISO 8601 when they come back in the ZGW wire format', function () {
    fakeDoorkomstZgw(deelZaakEigenschappen: [
        ['naam' => 'start_evenement', 'waarde' => '20260701100000'],
        ['naam' => 'eind_evenement', 'waarde' => '20260701180000'],
    ]);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();
    $stored = json_decode($deel->getRawOriginal('reference_data'), true);

    expect($stored['start_evenement'])->toBe('2026-07-01T10:00:00+02:00')
        ->and($stored['eind_evenement'])->toBe('2026-07-01T18:00:00+02:00');
});

test('does not create a doorkomst zaak when the passing gemeente has no doorkomst zaaktype', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstScenario(hoofdOwnInstance: true);

    // No doorkomst zaaktype configured for the passing municipality.
    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(0);
});

/**
 * Metadata for a source enkelvoudiginformatieobject on the hoofdzaak's instance.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sourceEioMeta(string $uuid, array $overrides = []): array
{
    return array_merge([
        'url' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/'.$uuid,
        'titel' => 'Situatietekening',
        'auteur' => 'Jan Jansen',
        'taal' => 'dut',
        'bestandsnaam' => 'situatie.pdf',
        'formaat' => 'application/pdf',
        'vertrouwelijkheidaanduiding' => 'vertrouwelijk',
        'creatiedatum' => '2026-06-15',
        'informatieobjecttype' => OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage',
    ], $overrides);
}

/**
 * Fake a hoofdzaak on its own instance (OWN_HOST) with one document, and a
 * deelzaak on main, so the document is copied across instances into main.
 */
function fakeCrossInstanceDocumentCopy(): void
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
        // One document on the hoofdzaak.
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobject' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1'],
        ]), 200),
        // The download must be matched before the EIO-metadata glob.
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1/download*' => Http::response('PDFBYTES', 200),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1*' => Http::response(sourceEioMeta('doc-1'), 200),
        // Source informatieobjecttype omschrijving (matched exactly on the target).
        OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage*' => Http::response([
            'url' => OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage',
            'omschrijving' => 'Bijlage',
        ], 200),
        // Target document types on the deel connection (main).
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage',
            'omschrijving' => 'Bijlage',
        ], 200),
        // Target documenten store + zaakinformatieobjecten link.
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/new-doc-1',
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
        ], 201),
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

test('copies documents cross-instance: downloads from the hoofdzaak and re-creates them in the deelzaak instance', function () {
    fakeCrossInstanceDocumentCopy();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // The source document was downloaded.
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_starts_with($request->url(), OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1/download'));

    // A new document was created in the target instance from the downloaded bytes,
    // with the target-mapped informatieobjecttype and copied metadata.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten')
        && $request->data()['inhoud'] === base64_encode('PDFBYTES')
        && $request->data()['informatieobjecttype'] === ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage'
        && $request->data()['titel'] === 'Situatietekening'
        && $request->data()['auteur'] === 'Jan Jansen'
        // Determined by the target connection (systemUploadDefault → zaakvertrouwelijk),
        // not copied from the source document (which is 'vertrouwelijk').
        && $request->data()['vertrouwelijkheidaanduiding'] === 'zaakvertrouwelijk'
        && $request->data()['bestandsomvang'] === strlen('PDFBYTES'));

    // The new copy is linked to the deelzaak, and the source url is never linked.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten')
        && ($request->data()['zaak'] ?? null) === ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'
        && ($request->data()['informatieobject'] ?? null) === ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/new-doc-1');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten')
        && ($request->data()['informatieobject'] ?? null) === OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1');
});

test('copies a document to another connection as the organisation of that connection', function () {
    // The hoofdzaak carries RSIN 123456789 on its own instance; the document is
    // copied into main, whose RSIN differs. The copy must carry main's RSIN.
    fakeCrossInstanceDocumentCopy();
    config(['zgw.connections.main.bronorganisatie_rsin' => '987654321']);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten')
        && ($request->data()['bronorganisatie'] ?? null) === '987654321');

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten')
        && ($request->data()['bronorganisatie'] ?? null) === '123456789');
});

/**
 * Fake a hoofdzaak on main with one document, and a deelzaak on main too, so
 * the document url is linked directly instead of copied.
 */
function fakeSameInstanceDocumentLink(): void
{
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1'], 201);
            }

            return Http::response(ZgwHttpFake::envelope([
                ['informatieobject' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1'],
            ]), 200);
        },
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

test('links the existing document url and does not copy when the deelzaak shares the hoofdzaak instance', function () {
    // Hoofdzaak on main; doorkomst zaaktype on main too, so both live in one
    // instance and the document url is directly linkable.
    fakeSameInstanceDocumentLink();

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1']);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // The original informatieobject url is linked directly.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten')
        && ($request->data()['informatieobject'] ?? null) === ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1');

    // No download and no re-upload happened.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/download'));
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten'));
});

test('leaves a linked document with its own RSIN when the deelzaak shares the hoofdzaak connection', function () {
    // Same connection: the document is linked, not re-created, so nothing is
    // written to the documenten API and its bronorganisatie stays as it is.
    fakeSameInstanceDocumentLink();
    config(['zgw.connections.main.bronorganisatie_rsin' => '987654321']);

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1']);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten')
        && ($request->data()['informatieobject'] ?? null) === ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1');

    Http::assertNotSent(fn ($request) => in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)
        && str_contains($request->url(), '/documenten/api/v1/'));
});

test('skips a document cross-instance when no target informatieobjecttype resolves, without failing the job', function () {
    Http::fake([
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobject' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1'],
        ]), 200),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1/download*' => Http::response('PDFBYTES', 200),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1*' => Http::response(sourceEioMeta('doc-1'), 200),
        OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage*' => Http::response([
            'url' => OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage',
            'omschrijving' => 'Bijlage',
        ], 200),
        // Target has no document types: nothing to map to.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([]), 200),
        // Target statustype so the initial status is still set.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1', 'volgnummer' => 1, 'omschrijving' => 'Ontvangen'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen*' => Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen/1'], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    // A document without a matching type is a configuration warning, not a
    // failed step, so the job does not fail for it.
    runDoorkomstJob($scenario['hoofdzaak'])->assertNotFailed();

    // The deelzaak is still created locally.
    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);

    // No document was re-created in the target instance.
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten'));

    // The initial status was still set.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen'));
});

test('a failing document copy does not abort the remaining documents, and fails the job afterwards', function () {
    Exceptions::fake();
    Http::fake([
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobject' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1'],
            ['informatieobject' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-2'],
        ]), 200),
        // First document's download fails; the second succeeds.
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1/download*' => Http::response('', 500),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1*' => Http::response(sourceEioMeta('doc-1'), 200),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-2/download*' => Http::response('BYTES2', 200),
        OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-2*' => Http::response(sourceEioMeta('doc-2', [
            'url' => OWN_HOST.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-2',
        ]), 200),
        OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage*' => Http::response([
            'url' => OWN_HOST.'/catalogi/api/v1/informatieobjecttypen/src-bijlage',
            'omschrijving' => 'Bijlage',
        ], 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktype-informatieobjecttypen*' => Http::response(ZgwHttpFake::envelope([
            ['informatieobjecttype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/informatieobjecttypen/tgt-bijlage',
            'omschrijving' => 'Bijlage',
        ], 200),
        ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten/new-doc-2',
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten/1',
        ], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    $job = runDoorkomstJob($scenario['hoofdzaak']);

    // Exactly one document was re-created (the second): the failure of the first
    // did not abort the loop.
    $documentenPosts = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'POST'
            && str_starts_with($pair[0]->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten'))
        ->count();
    expect($documentenPosts)->toBe(1);

    // The deelzaak is still created locally.
    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);

    // The failed copy is reported, and once the other documents had their turn
    // the documenten step fails: the deelzaak is incomplete, so the job is
    // marked as failed without a retry.
    Exceptions::assertReported(fn (DoorkomstDeelzaakIncompleteException $e) => $e->failedSteps === ['documenten']);
    $job->assertFailedWith(DoorkomstZakenIncompleteException::class);
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

/**
 * Cross-instance doorkomst whose receiving zaaksysteem can be made to fail the
 * read-back of the new deelzaak, the catalogus read of its eigenschappen, the
 * initiator rol, or writing its one eigenschap with a given status. The deel
 * catalogus has an eigenschap, an initiator roltype and an initial
 * statustype, so every step has something to send. Counts how often a
 * deelzaak is created.
 */
function fakeDoorkomstWithInterruptions(bool &$readBackFails, bool $eigenschappenCatalogusFails, int &$created, bool $initiatorFails = false, ?int $eigenschapFailsWith = null): void
{
    $serverError = fn () => Http::response([
        'type' => 'https://zgw.example.com/ref/fouten/APIException/',
        'code' => 'error',
        'title' => 'A server error occurred.',
        'status' => 500,
        'detail' => 'A server error occurred.',
        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000000',
    ], 500);

    Http::fake([
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
            '_expand' => ['eigenschappen' => [['naam' => 'naam_evenement', 'waarde' => 'Zomerfeest']]],
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => $eigenschappenCatalogusFails
            ? $serverError()
            : Http::response(ZgwHttpFake::envelope([
                ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-naam', 'naam' => 'naam_evenement'],
            ]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/roltypen/init', 'omschrijvingGeneriek' => 'initiator'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/ontvangen', 'volgnummer' => 1, 'omschrijving' => 'Ontvangen'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen*' => $initiatorFails
            ? $serverError()
            : Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/rollen/1'], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen*' => Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen/1'], 201),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use (&$readBackFails, &$created, $serverError, $eigenschapFailsWith) {
            if ($request->method() === 'POST') {
                $path = (string) parse_url($request->url(), PHP_URL_PATH);
                if ($path === '/zaken/api/v1/zaken') {
                    $created++;
                }
                if ($eigenschapFailsWith !== null && str_ends_with($path, '/zaakeigenschappen')) {
                    return Http::response(['code' => 'error', 'title' => 'Refused.', 'status' => $eigenschapFailsWith], $eigenschapFailsWith);
                }

                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return $readBackFails ? $serverError() : Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

test('does not create the deelzaak a second time when the job is retried after a failure past its creation', function () {
    // The deelzaak used to be registered locally only after every later step,
    // so a failure in between left no trace and each retry created another one
    // in the receiving zaaksysteem.
    Bus::fake([CreateConceptAdviceQuestions::class]);
    $readBackFails = true;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: false, created: $created);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    expect(fn () => CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']))
        ->toThrow(DoorkomstZakenIncompleteException::class);

    // Registered straight away, but not yet read back.
    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->sole();
    expect($deel->public_id)->toBeNull()
        ->and($deel->reference_data->naam_evenement)->toBe($scenario['hoofdzaak']->reference_data->naam_evenement);
    Bus::assertNotDispatched(fn (CreateConceptAdviceQuestions $job) => $job->zaak->is($deel));

    $readBackFails = false;
    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect($created)->toBe(1)
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1)
        // The retry completed the registration it found unfinished.
        ->and($deel->fresh()->public_id)->toBe('DEEL-1')
        ->and($deel->fresh()->reference_data->start_evenement)->toBe('2026-07-01 10:00');
    // The creation hooks of the zaak run once, on the completed registration.
    Bus::assertDispatchedTimes(fn (CreateConceptAdviceQuestions $job) => $job->zaak->is($deel), 1);
});

/** Whether the job posted to the given Zaken API collection. */
function postedTo(string $collection): bool
{
    return collect(Http::recorded())->contains(fn ($pair) => $pair[0]->method() === 'POST'
        && parse_url($pair[0]->url(), PHP_URL_PATH) === '/zaken/api/v1/'.$collection);
}

test('logs the creation of a deelzaak with its initial values', function () {
    // The deelzaak is registered without model events, so its created entry in
    // the activity log is written explicitly.
    fakeDoorkomstZgw();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->sole();
    $created = Activity::query()
        ->where('subject_type', $deel->getMorphClass())
        ->where('subject_id', $deel->getKey())
        ->where('event', 'created')
        ->get();

    expect($created)->toHaveCount(1);
    $attributes = $created->first()->properties['attributes'];
    expect($attributes['hoofdzaak_id'])->toBe($scenario['hoofdzaak']->id)
        ->and($attributes['zaaktype_id'])->toBe($deel->zaaktype_id)
        ->and($attributes['zgw_zaak_url'])->toBe(ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1')
        ->and($attributes['zgw_connection'])->toBe('main')
        ->and($attributes)->not->toHaveKey('form_state_snapshot');
});

test('completes an unfinished registration on the connection the deelzaak was created on', function () {
    // An earlier attempt registered the deelzaak on the doorkomst gemeente's own
    // instance. When its zaaktype resolves to another connection by the time
    // of the retry, the read-back still has to go where the deelzaak lives.
    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaken/deel-1*' => Http::response(array_merge(deelZaakReadResponse(), [
            'url' => OWN_HOST.'/zaken/api/v1/zaken/deel-1',
        ]), 200),
        '*' => Http::response([], 200),
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update(['zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1']);
    MunicipalityZgwConnection::factory()->active()->create(['municipality_id' => $scenario['passing']->id]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        // Resolves to main now.
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    $deel = Zaak::withoutEvents(fn () => Zaak::factory()->create([
        'public_id' => null,
        'zgw_connection' => "gemeente_{$scenario['passing']->id}",
        'zgw_zaak_url' => OWN_HOST.'/zaken/api/v1/zaken/deel-1',
        'zaaktype_id' => $doorkomstZaaktype->id,
        'role' => ZaaktypeRole::Doorkomst,
        'hoofdzaak_id' => $scenario['hoofdzaak']->id,
    ]));

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect($deel->fresh()->public_id)->toBe('DEEL-1')
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);
    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_starts_with($request->url(), OWN_HOST.'/zaken/api/v1/zaken/deel-1'));
});

/**
 * Run the job with fake queue interactions, so a test can tell a job that
 * marks itself as failed apart from one that throws for a retry.
 */
function runDoorkomstJob(Zaak $hoofdzaak): CreateDoorkomstZaken
{
    $job = (new CreateDoorkomstZaken($hoofdzaak))->withFakeQueueInteractions();
    $job->handle(app(ZaakeigenschappenMap::class));

    return $job;
}

test('runs the other steps when one fails after the deelzaak was created, and fails the job afterwards', function () {
    // Reading the eigenschappen catalogus fails. The steps after it still run,
    // and the job still ends as failed so the incomplete deelzaak stays visible.
    Exceptions::fake();
    Bus::fake([CreateConceptAdviceQuestions::class]);
    $readBackFails = false;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: true, created: $created);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    runDoorkomstJob($scenario['hoofdzaak'])->assertFailedWith(DoorkomstZakenIncompleteException::class);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->sole();
    expect($created)->toBe(1)
        ->and($deel->public_id)->toBe('DEEL-1')
        // The failing eigenschappen step does not cost the deelzaak the steps
        // after it.
        ->and(postedTo('rollen'))->toBeTrue()
        ->and(postedTo('statussen'))->toBeTrue();
    Exceptions::assertReported(ApiRequestException::class);
    Exceptions::assertReported(fn (DoorkomstDeelzaakIncompleteException $e) => $e->failedSteps === ['eigenschappen']);
    Bus::assertDispatchedTimes(fn (CreateConceptAdviceQuestions $job) => $job->zaak->is($deel), 1);
});

test('still sets the initial status when registering the initiator fails', function () {
    // Each step after the creation is tried on its own: no step depends on
    // another, so one failing must not skip the ones after it.
    Exceptions::fake();
    $readBackFails = false;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: false, created: $created, initiatorFails: true);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    runDoorkomstJob($scenario['hoofdzaak'])->assertFailedWith(DoorkomstZakenIncompleteException::class);

    expect(postedTo('rollen'))->toBeTrue()
        ->and(postedTo('statussen'))->toBeTrue()
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->sole()->public_id)->toBe('DEEL-1');
    Exceptions::assertReported(ApiRequestException::class);
    Exceptions::assertReported(fn (DoorkomstDeelzaakIncompleteException $e) => $e->failedSteps === ['initiator']);
});

test('keeps an incomplete deelzaak visible when its read-back fails as well', function () {
    // A step fails and so does the read-back. Passing the read-back failure on
    // for an automatic retry would let the retry complete the registration,
    // succeed and hide the incomplete deelzaak; the job is marked as failed.
    Exceptions::fake();
    $readBackFails = true;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: true, created: $created);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    runDoorkomstJob($scenario['hoofdzaak'])->assertFailed();
    Exceptions::assertReported(fn (DoorkomstDeelzaakIncompleteException $e) => $e->failedSteps === ['eigenschappen', 'registratie']);

    // A manual retry of the failed job completes the registration and creates
    // nothing new.
    $readBackFails = false;
    runDoorkomstJob($scenario['hoofdzaak'])->assertNotFailed();

    expect($created)->toBe(1)
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->sole()->public_id)->toBe('DEEL-1');
});

test('marks the job as failed without a retry when its only failure is an incomplete deelzaak', function () {
    // A retry would skip the registered deelzaak and succeed, which removes the
    // failed job again. Marking the job as failed keeps it visible.
    Exceptions::fake();
    $readBackFails = false;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: false, created: $created, initiatorFails: true);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    runDoorkomstJob($scenario['hoofdzaak'])->assertFailedWith(DoorkomstZakenIncompleteException::class);
});

/**
 * Two passing municipalities on main: creating the deelzaak of the first one
 * (dk-m) fails, and the second one (dk-m2) gets its deelzaak, but setting its
 * initial status fails, so it is incomplete.
 */
function fakeDoorkomstMissingAndIncomplete(): void
{
    $created = 0;
    $serverError = fn () => Http::response([
        'type' => 'https://zgw.example.com/ref/fouten/APIException/',
        'code' => 'error',
        'title' => 'A server error occurred.',
        'status' => 500,
        'detail' => 'A server error occurred.',
        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000000',
    ], 500);

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/ontvangen', 'volgnummer' => 1, 'omschrijving' => 'Ontvangen'],
        ]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen*' => $serverError(),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use (&$created, $serverError) {
            if ($request->method() === 'POST') {
                if (($request->data()['zaaktype'] ?? null) === doorkomstZaaktypeUrl('dk-m')) {
                    return $serverError();
                }

                $created++;

                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-'.$created], 201);
            }

            $slug = basename((string) parse_url($request->url(), PHP_URL_PATH));

            return Http::response(array_merge(deelZaakReadResponse(), [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.$slug,
                'identificatie' => strtoupper($slug),
            ]), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

test('marks the job as failed without a retry when one municipality is missing its deelzaak and another one is incomplete', function () {
    // A retry would create the missing deelzaak, skip the incomplete one and
    // succeed, which removes the failed job and with it the only sign of the
    // incomplete deelzaak. The job is marked as failed instead, and the
    // message says how to create the missing one.
    Exceptions::fake();
    fakeDoorkomstMissingAndIncomplete();
    secondPassingMunicipality();
    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
        lineGeometry([[0.5, -2.0], [3.5, -2.0]]),
    ]);

    $job = runDoorkomstJob($hoofdzaak);

    // assertFailedWith() only checks the class, so the exception itself is
    // read from the fake queue job.
    $job->assertFailedWith(DoorkomstZakenIncompleteException::class);
    $failure = $job->job->failedWith;
    expect($failure->failedMunicipalities)->toHaveCount(2)
        ->and($failure->getMessage())->toContain('not retried automatically')
        ->and($failure->getMessage())->toContain("zaak:create-doorkomst-zaken {$hoofdzaak->public_id}");
    expect(localDeelzaakZaaktypen($hoofdzaak))->toBe([doorkomstZaaktypeUrl('dk-m2')]);
});

test('throws for an automatic retry when deelzaken are only missing', function () {
    // Nothing is incomplete, so the queue's retry can create the missing
    // deelzaak and nothing gets lost when it succeeds.
    Exceptions::fake();
    $failing = true;
    fakeDoorkomstZgwOnMainFailingFor(doorkomstZaaktypeUrl('dk-m'), $failing);
    $hoofdzaak = threeRouteHoofdZaak();

    $job = (new CreateDoorkomstZaken($hoofdzaak))->withFakeQueueInteractions();

    expect(fn () => $job->handle(app(ZaakeigenschappenMap::class)))
        ->toThrow(fn (DoorkomstZakenIncompleteException $e) => str_contains($e->getMessage(), 'a retry only creates the ones that are still missing'));
    $job->assertNotFailed();
});

test('fails the job when an eigenschap fails for another reason than an invalid value, and a retry creates nothing new', function (int $status) {
    // Only a validation error on an eigenschap is a refusal of that one value.
    // A server error or a refused authorisation means the eigenschappen were
    // not written, so the deelzaak is incomplete: the other steps still run,
    // then the job fails. A retry skips the registered deelzaak.
    Exceptions::fake();
    $readBackFails = false;
    $created = 0;
    fakeDoorkomstWithInterruptions($readBackFails, eigenschappenCatalogusFails: false, created: $created, eigenschapFailsWith: $status);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['form_state_snapshot' => routeSnapshotWithValues([
        'watIsUwVoornaam' => 'Jan',
        'watIsUwAchternaam' => 'Jansen',
    ])]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    runDoorkomstJob($scenario['hoofdzaak'])->assertFailedWith(DoorkomstZakenIncompleteException::class);

    expect(postedTo('rollen'))->toBeTrue()
        ->and(postedTo('statussen'))->toBeTrue();
    Exceptions::assertNotReported(DoorkomstZaakeigenschapRejectedException::class);
    Exceptions::assertReported(fn (DoorkomstDeelzaakIncompleteException $e) => $e->failedSteps === ['eigenschappen']);

    // The retry finds the registered deelzaak and leaves it alone.
    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect($created)->toBe(1)
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);
})->with([
    'a server error' => [500],
    'a refused authorisation' => [403],
]);

/**
 * ---------------------------------------------------------------------------
 * Several routes on one map
 * ---------------------------------------------------------------------------
 *
 * An event can have more than one route drawn on the map. Every drawn route
 * has to produce doorkomst deelzaken for the municipalities it crosses. The
 * result is the union over all routes, minus the start and end municipalities
 * of all routes together: a municipality where any route begins or ends never
 * gets a deelzaak, not even when another route merely passes through it.
 *
 * These cases extend the fixture of doorkomstScenario() with a second passing
 * municipality south of it:
 *
 *   y  3..4    . . . . . . . Eindgemeente (3..4)
 *   y  1.5..2.5      Doorkomstgemeente (1.5..2.5)
 *   y  0..1    Hoofdgemeente (0..1)
 *   y -2.5..-1.5     Tweede doorkomstgemeente (1.5..2.5)
 */

/** A bare GeoJSON LineString geometry. */
function lineGeometry(array $coordinates, string $type = 'LineString'): array
{
    return ['type' => $type, 'coordinates' => $coordinates];
}

/**
 * Current map state: one Map component holding N drawn features. This is what
 * the form writes since the Repeater around the route map was dropped.
 */
function routeMapSnapshot(array $geometries): array
{
    return ['values' => ['routesOpKaart' => [
        'lat' => 0.5,
        'lng' => 0.5,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => array_map(static fn (array $geometry) => [
                'type' => 'Feature',
                'properties' => [],
                'geometry' => $geometry,
            ], $geometries),
        ],
    ]]];
}

/**
 * Old draft state: one Repeater row per route, each row holding its own Map
 * state. Existing drafts still carry this shape.
 *
 * @param  list<list<array<string, mixed>>>  $rows  one list of geometries per row
 */
function routeRepeaterSnapshot(array $rows): array
{
    return ['values' => ['routesOpKaart' => array_map(
        static fn (array $geometries) => ['routeVanHetEvenement' => routeMapSnapshot($geometries)['values']['routesOpKaart']],
        $rows,
    )]];
}

/**
 * Give the end municipality of the diagonal route its own doorkomst zaaktype,
 * so it is a candidate that only the start/end rule can keep out.
 */
function withEindgemeenteDoorkomstZaaktype(): void
{
    $eind = Municipality::where('name', 'Eindgemeente')->firstOrFail();

    Zaaktype::factory()->create([
        'municipality_id' => $eind->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-eind',
        'is_active' => true,
    ]);
}

/** A second passing municipality with its own doorkomst zaaktype on main. */
function secondPassingMunicipality(): Municipality
{
    $municipality = Municipality::factory()->create([
        'name' => 'Tweede doorkomstgemeente',
        'geometry' => multipolygon([[1.5, -2.5], [1.5, -1.5], [2.5, -1.5], [2.5, -2.5], [1.5, -2.5]]),
    ]);

    Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m2',
        'is_active' => true,
    ]);

    return $municipality;
}

/**
 * Hoofdzaak and deelzaken all on main, with a distinct url per created
 * deelzaak so several of them can be told apart.
 */
function fakeDoorkomstZgwOnMain(): void
{
    $created = 0;

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use (&$created) {
            if ($request->method() === 'POST') {
                $created++;

                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-'.$created], 201);
            }

            // Read back the deelzaak that was actually asked for, so several
            // deelzaken keep their own url and identificatie.
            $slug = basename((string) parse_url($request->url(), PHP_URL_PATH));

            return Http::response(array_merge(deelZaakReadResponse(), [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.$slug,
                'identificatie' => strtoupper($slug),
            ]), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

/**
 * A hoofdzaak on main whose form state holds the given route geometries.
 */
function multiRouteHoofdZaak(array $geometries): Zaak
{
    $scenario = doorkomstScenario(hoofdOwnInstance: false);
    $scenario['hoofdzaak']->update([
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
        'form_state_snapshot' => routeMapSnapshot($geometries),
    ]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    return $scenario['hoofdzaak'];
}

/**
 * The zaaktype urls the job actually created a deelzaak for, sorted so the
 * assertion does not depend on the order the municipalities come back in.
 *
 * @return list<string>
 */
function createdDeelzaakZaaktypen(): array
{
    $zaaktypen = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'POST'
            && parse_url($pair[0]->url(), PHP_URL_PATH) === '/zaken/api/v1/zaken')
        ->map(fn ($pair) => (string) ($pair[0]->data()['zaaktype'] ?? ''))
        ->values()
        ->all();

    sort($zaaktypen);

    return $zaaktypen;
}

function doorkomstZaaktypeUrl(string $slug): string
{
    return ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/'.$slug;
}

test('creates doorkomst zaken for the crossed municipalities of every drawn route', function () {
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipality();

    // Route 1 crosses the first doorkomst municipality, route 2 the second one.
    // Neither starts or ends in a municipality that the other one crosses.
    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
        lineGeometry([[0.5, -2.0], [3.5, -2.0]]),
    ]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m2'),
    ]);
});

test('never creates a deelzaak for a municipality another route starts in', function () {
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipality();

    // Route 2 starts inside the municipality that route 1 passes through, and
    // runs south into the second doorkomst municipality. Being a start
    // municipality of the event wins, so only the second one gets a deelzaak.
    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
        lineGeometry([[2.0, 2.0], [2.0, -3.5]]),
    ]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([doorkomstZaaktypeUrl('dk-m2')]);
});

test('reads a MultiLineString route instead of crashing on it', function () {
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipality();

    // One route drawn in several parts: it has no start point of its own, so
    // the parts have to be walked to find the start and end municipalities.
    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([
            [[0.5, 0.5], [3.5, 3.5]],
            [[0.5, -2.0], [3.5, -2.0]],
        ], 'MultiLineString'),
    ]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m2'),
    ]);
});

test('a single drawn route still yields exactly the municipality it crosses', function () {
    // Regression anchor: one route in the current map state shape behaves the
    // same as it always did. The tests above this block cover the same for the
    // bare-geometry snapshot shape that existing drafts still hold.
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipality();

    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
    ]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([doorkomstZaaktypeUrl('dk-m')]);
});

test('excludes the municipality a route ends in, even when it has a doorkomst zaaktype', function () {
    // Without a doorkomst zaaktype an end municipality is skipped for the wrong
    // reason, so give it one: only the boundary rule may keep it out.
    fakeDoorkomstZgwOnMain();

    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
    ]);
    withEindgemeenteDoorkomstZaaktype();

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    $created = createdDeelzaakZaaktypen();

    expect($created)->not->toContain(doorkomstZaaktypeUrl('dk-eind'))
        ->and($created)->toBe([doorkomstZaaktypeUrl('dk-m')]);
});

test('reads every route from an old repeater draft state', function () {
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipality();

    // Drafts made before the Repeater around the route map was dropped hold one
    // row per route, each row carrying its own Map state.
    $hoofdzaak = multiRouteHoofdZaak([]);
    $hoofdzaak->update(['form_state_snapshot' => routeRepeaterSnapshot([
        [lineGeometry([[0.5, 0.5], [3.5, 3.5]])],
        [lineGeometry([[0.5, -2.0], [3.5, -2.0]])],
    ])]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m2'),
    ]);
});

/**
 * ---------------------------------------------------------------------------
 * Eigenschap namen across two koppelingen
 * ---------------------------------------------------------------------------
 *
 * The hoofdzaak and the doorkomst zaaktype each have their own koppeling, and
 * a koppeling decides what the catalogus on that side calls an eigenschap. So
 * a value has to travel hoofdzaak naam → logical key → deel naam, both when it
 * is copied onto the deelzaak and when the created deelzaak is read back into
 * reference_data. Matching the namen literally skips the value in silence.
 */

/** A doorkomst zaaktype for the passing municipality, with its own eigenschap map. */
function doorkomstZaaktypeWithEigenschapMap(Municipality $passing, array $eigenschapMap): Zaaktype
{
    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $passing->id,
        'identificatie' => 'DK-1',
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m',
        'is_active' => true,
    ]);

    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $passing->id,
        'role' => ZaaktypeRole::Doorkomst,
        'zaaktype_identificatie' => 'DK-1',
        'eigenschap_map' => $eigenschapMap,
    ]));

    return $zaaktype;
}

/** Drop the evenement dates from the hoofdzaak so nothing can fall back to them. */
function withoutEvenementDates(Zaak $hoofdzaak): void
{
    $hoofdzaak->update([
        'reference_data' => new ZaakReferenceData(
            ...array_merge($hoofdzaak->reference_data->toArray(), [
                'start_evenement' => null,
                'eind_evenement' => null,
                'naam_evenement' => null,
            ])
        ),
    ]);
}

test('reads the created deelzaak back onto the logical keys when its koppeling renames the eigenschappen', function () {
    // The deel catalogus names the eigenschappen its own way, so the read-back
    // has to be translated or the values never reach reference_data.
    fakeDoorkomstZgw(deelZaakEigenschappen: [
        ['naam' => '1.start evenement', 'waarde' => '2026-07-01 10:00'],
        ['naam' => '2.eind evenement', 'waarde' => '2026-07-01 18:00'],
        ['naam' => '3.naam evenement', 'waarde' => 'Zomerfeest'],
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withoutEvenementDates($scenario['hoofdzaak']);
    doorkomstZaaktypeWithEigenschapMap($scenario['passing'], [
        'start_evenement' => '1.start evenement',
        'eind_evenement' => '2.eind evenement',
        'naam_evenement' => '3.naam evenement',
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->start_evenement)->toBe('2026-07-01 10:00')
        ->and($deel->reference_data->eind_evenement)->toBe('2026-07-01 18:00')
        ->and($deel->reference_data->naam_evenement)->toBe('Zomerfeest');
});

test('a doorkomst koppeling that uses the logical keys reads back exactly as before', function () {
    // Regression anchor for the read-back: an identity map changes nothing.
    fakeDoorkomstZgw();

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    doorkomstZaaktypeWithEigenschapMap($scenario['passing'], [
        'start_evenement' => 'start_evenement',
        'eind_evenement' => 'eind_evenement',
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->start_evenement)->toBe('2026-07-01 10:00')
        ->and($deel->reference_data->eind_evenement)->toBe('2026-07-01 18:00');
});

/**
 * Cross-instance doorkomst where the hoofdzaak read carries the given
 * eigenschappen and the deel catalogus exposes the given eigenschap namen.
 *
 * @param  array<string, string>  $hoofdEigenschappen  hoofdzaak naam => waarde
 * @param  array<string, string>  $deelCatalogus  deel catalogus naam => eigenschap slug
 * @param  array<string, string>  $deelFormaten  deel catalogus naam => specificatie formaat
 * @param  list<string>  $rejectedSlugs  eigenschap slugs the deel zaaksysteem refuses with a 400
 */
function fakeDoorkomstWithCatalogusEigenschappen(array $hoofdEigenschappen, array $deelCatalogus, array $deelFormaten = [], array $rejectedSlugs = []): void
{
    $expanded = [];
    foreach ($hoofdEigenschappen as $naam => $waarde) {
        $expanded[] = ['naam' => $naam, 'waarde' => $waarde];
    }

    $catalogus = [];
    foreach ($deelCatalogus as $naam => $slug) {
        $eigenschap = [
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/'.$slug,
            'naam' => $naam,
        ];
        if (isset($deelFormaten[$naam])) {
            $eigenschap['specificatie'] = ['formaat' => $deelFormaten[$naam]];
        }
        $catalogus[] = $eigenschap;
    }

    Http::fake([
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
            '_expand' => ['eigenschappen' => $expanded],
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope($catalogus), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use ($rejectedSlugs) {
            if ($request->method() === 'POST') {
                $eigenschap = (string) ($request->data()['eigenschap'] ?? '');
                if (str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/zaakeigenschappen')
                    && in_array(basename($eigenschap), $rejectedSlugs, true)) {
                    return Http::response(rejectedEigenschapResponse(), 400);
                }

                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

/** A 400 for a zaakeigenschap whose waarde does not match the formaat of its eigenschap. */
function rejectedEigenschapResponse(): array
{
    return [
        'type' => 'https://zgw.example.com/ref/fouten/ValidationError/',
        'code' => 'invalid',
        'title' => 'Invalid input.',
        'status' => 400,
        'detail' => '',
        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000000',
        'invalidParams' => [
            [
                'name' => 'eigenschap',
                'code' => 'invalid',
                // A validator may quote the rejected value in its reason.
                'reason' => 'Value 2026-07-01 is not in the required date-time format.',
            ],
        ],
    ];
}

/** The eigenschap urls the job posted onto the deelzaak, keyed by waarde. */
function copiedZaakeigenschappen(): array
{
    return collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'POST'
            && str_contains((string) parse_url($pair[0]->url(), PHP_URL_PATH), '/zaakeigenschappen'))
        ->mapWithKeys(fn ($pair) => [(string) ($pair[0]->data()['waarde'] ?? '') => (string) ($pair[0]->data()['eigenschap'] ?? '')])
        ->all();
}

test('copies an eigenschap onto the deelzaak even when both koppelingen rename it differently', function () {
    // Hoofdzaak catalogus calls it "A.…", deel catalogus "B.…". Only the logical
    // key in between connects the two.
    fakeDoorkomstWithCatalogusEigenschappen(
        hoofdEigenschappen: ['A.naam evenement' => 'Zomerfeest', 'A.risico klasse' => 'C'],
        deelCatalogus: ['B.naam evenement' => 'b-naam', 'B.risico klasse' => 'b-risico'],
    );

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->zaaktype->update(['identificatie' => 'HZ-1']);
    MunicipalityZaaktypeMapping::withoutEvents(fn () => MunicipalityZaaktypeMapping::create([
        'municipality_id' => $scenario['hoofd']->id,
        'role' => ZaaktypeRole::Vergunning,
        'zaaktype_identificatie' => 'HZ-1',
        'eigenschap_map' => [
            'naam_evenement' => 'A.naam evenement',
            'risico_classificatie' => 'A.risico klasse',
        ],
    ]));
    doorkomstZaaktypeWithEigenschapMap($scenario['passing'], [
        'naam_evenement' => 'B.naam evenement',
        'risico_classificatie' => 'B.risico klasse',
    ]);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(copiedZaakeigenschappen())->toBe([
        'Zomerfeest' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/b-naam',
        'C' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/b-risico',
    ]);
});

test('copies eigenschappen by their own naam when neither zaaktype has a koppeling', function () {
    // Regression anchor for the copy: without any eigenschap map the namen match
    // literally, exactly as they always did.
    fakeDoorkomstWithCatalogusEigenschappen(
        hoofdEigenschappen: ['naam_evenement' => 'Zomerfeest'],
        deelCatalogus: ['naam_evenement' => 'plain-naam'],
    );

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(copiedZaakeigenschappen())->toBe([
        'Zomerfeest' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/plain-naam',
    ]);
});

test('formats a copied eigenschap for the formaat of the receiving zaaktype', function () {
    // The hoofdzaak returns its eigenschappen in the wire form of its own
    // catalogus. The receiving catalogus can declare a different formaat for the
    // same eigenschap, and a backend that enforces it rejects anything else.
    fakeDoorkomstWithCatalogusEigenschappen(
        hoofdEigenschappen: [
            // A datum on the hoofdzaak side, a datum_tijd on the receiving side.
            'start_evenement' => '20260701',
            // A datum_tijd on the hoofdzaak side, a datum on the receiving side.
            'eind_evenement' => '20260701180000',
            // ISO 8601 text on the hoofdzaak side.
            'start_opbouw' => '2026-07-01T08:30:00+02:00',
            // Already in the receiving wire form.
            'eind_opbouw' => '20260701093000',
            // A tekst eigenschap that happens to look like a date.
            'naam_evenement' => '20260702',
        ],
        deelCatalogus: [
            'start_evenement' => 'deel-start',
            'eind_evenement' => 'deel-eind',
            'start_opbouw' => 'deel-start-opbouw',
            'eind_opbouw' => 'deel-eind-opbouw',
            'naam_evenement' => 'deel-naam',
        ],
        deelFormaten: [
            'start_evenement' => 'datum_tijd',
            'eind_evenement' => 'datum',
            'start_opbouw' => 'datum_tijd',
            'eind_opbouw' => 'datum_tijd',
            'naam_evenement' => 'tekst',
        ],
    );

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    expect(copiedZaakeigenschappen())->toBe([
        '20260701000000' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-start',
        '20260701' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-eind',
        '20260701083000' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-start-opbouw',
        '20260701093000' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-eind-opbouw',
        '20260702' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/deel-naam',
    ]);
});

test('keeps creating the deelzaak when the receiving zaaksysteem refuses one eigenschap', function () {
    // One refused eigenschap used to abort the deelzaak half way and fail the
    // job. It is reported instead, and the rest of the deelzaak follows.
    Exceptions::fake();
    fakeDoorkomstWithCatalogusEigenschappen(
        hoofdEigenschappen: ['start_evenement' => '2026-07-01', 'naam_evenement' => 'Zomerfeest'],
        deelCatalogus: ['start_evenement' => 'deel-start', 'naam_evenement' => 'deel-naam'],
        rejectedSlugs: ['deel-start'],
    );

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // The other eigenschap is still sent, after the refused one.
    expect(copiedZaakeigenschappen())->toHaveKey('Zomerfeest')
        // The deelzaak is registered: the job went on past the refusal.
        ->and(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);

    Exceptions::assertNotReported(DoorkomstZakenIncompleteException::class);
    Exceptions::assertReported(function (DoorkomstZaakeigenschapRejectedException $e) {
        return $e->eigenschap === 'start_evenement'
            && $e->response['status'] === 400
            && $e->response['invalid_params'] === [['name' => 'eigenschap', 'code' => 'invalid']]
            && str_contains($e->getMessage(), 'start_evenement')
            // Neither the value that was sent nor the reason quoting it.
            && ! str_contains($e->getMessage(), '2026')
            && ! str_contains((string) json_encode($e->context()), '2026-07-01');
    });
});

test('takes the event name from the hoofdzaak when the doorkomst zaaktype does not carry it', function () {
    // A doorkomst zaaktype whose catalogus knows none of the evenement
    // eigenschappen: the deelzaak read comes back without a name, which left
    // every deelzaak of the route nameless in the lists, exports and
    // notifications. The hoofdzaak reference_data holds the name, because it is
    // built from the form state rather than from ZGW.
    fakeDoorkomstZgw(deelZaakEigenschappen: []);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->naam_evenement)
        ->toBe($scenario['hoofdzaak']->reference_data->naam_evenement)
        ->not->toBeNull();
});

test('keeps the deelzaak event name when the doorkomst zaaktype does carry it', function () {
    // The fallback must never overwrite a name the deelzaak read supplies.
    fakeDoorkomstZgw(deelZaakEigenschappen: [
        ['naam' => 'naam_evenement', 'waarde' => 'Doorkomst eigen naam'],
    ]);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->naam_evenement)->toBe('Doorkomst eigen naam');
});

test('creates a deelzaak for every passing gemeente even when the hoofdzaak has no event name', function () {
    // The zaak observer notifies the municipality handlers synchronously, so a
    // notification that could not be built aborted the whole job: the gemeente
    // being handled at that moment got its ZGW deelzaak but no local record,
    // and every gemeente after it got nothing at all.
    fakeDoorkomstZgw(deelZaakEigenschappen: []);
    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    withPassingDoorkomstZaaktype($scenario['passing']);

    $scenario['hoofdzaak']->update([
        'reference_data' => new ZaakReferenceData(
            ...array_merge($scenario['hoofdzaak']->reference_data->toArray(), [
                'naam_evenement' => null,
            ])
        ),
    ]);

    // A handler on the passing gemeente, so the notification is actually built.
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $scenario['passing']->users()->attach($reviewer);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->reference_data->naam_evenement)->toBeNull()
        ->and($deel->public_id)->toBe('DEEL-1');
});

/**
 * ---------------------------------------------------------------------------
 * The doorkomst zaaktype and the connection it is sent to
 * ---------------------------------------------------------------------------
 *
 * A doorkomst zaaktype url is only resolvable by the instance whose catalogus
 * holds it. The runtime connection of a municipality routes to main whenever its
 * own connection cannot be used, so without the fallback the deelzaak was created
 * on main while the payload carried a url from the municipality's own catalogus,
 * which main rejects because it does not know that service. The same url is used
 * for the catalogi lookups that follow the create, so the whole deelzaak fails.
 */

/**
 * The passing municipality runs its own connection that cannot be used (never
 * activated) and keeps its doorkomst zaaktype in its own catalogus. The main
 * catalogus optionally holds the counterpart the fallback can move to.
 *
 * @return array{hoofd: Municipality, passing: Municipality, hoofdzaak: Zaak, own: Zaaktype, main: ?Zaaktype}
 */
function doorkomstUnusableKoppelingScenario(bool $withMainCounterpart): array
{
    $scenario = doorkomstScenario(hoofdOwnInstance: true);

    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'activated_at' => null,
    ]);

    $own = Zaaktype::factory()->create([
        'municipality_id' => $scenario['passing']->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$scenario['passing']->id}",
        'zgw_zaaktype_url' => OWN_HOST.'/catalogi/api/v1/zaaktypen/dk-own',
        'is_active' => true,
    ]);

    $main = $withMainCounterpart ? Zaaktype::factory()->create([
        'name' => 'Doorkomst gemeente '.$scenario['passing']->name,
        'municipality_id' => null,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-main',
        'is_active' => true,
    ]) : null;

    return array_merge($scenario, ['own' => $own, 'main' => $main]);
}

test('creates the deelzaak with a main zaaktype when the doorkomst gemeente cannot use its own instance', function () {
    fakeDoorkomstZgw();
    $scenario = doorkomstUnusableKoppelingScenario(withMainCounterpart: true);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // The payload carries a zaaktype of the instance it is posted to.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken')
        && $request['zaaktype'] === ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-main');

    // And the local deelzaak records the zaaktype that was actually used.
    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->value('zaaktype_id'))
        ->toBe($scenario['main']->id);
});

test('refuses to send a doorkomst zaaktype url to an instance that does not host it', function () {
    Exceptions::fake();
    fakeDoorkomstZgw();
    $scenario = doorkomstUnusableKoppelingScenario(withMainCounterpart: false);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // Nothing is created anywhere, and the failure names both configurations
    // instead of arriving as a validation error from the receiving side.
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    Exceptions::assertReported(ZaaktypeConnectionMismatchException::class);

    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->exists())->toBeFalse();
});

/**
 * A second passing municipality in the same state: a koppeling it cannot use, a
 * doorkomst zaaktype only its own catalogus holds, and nothing on main to fall
 * back to.
 */
function secondPassingMunicipalityWithUnusableKoppeling(): Municipality
{
    $municipality = Municipality::factory()->create([
        'name' => 'Tweede doorkomstgemeente',
        'geometry' => multipolygon([[1.5, -2.5], [1.5, -1.5], [2.5, -1.5], [2.5, -2.5], [1.5, -2.5]]),
    ]);

    MunicipalityZgwConnection::factory()->create([
        'municipality_id' => $municipality->id,
        'activated_at' => null,
    ]);

    Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => "gemeente_{$municipality->id}",
        'zgw_zaaktype_url' => OWN_HOST.'/catalogi/api/v1/zaaktypen/dk-m2-own',
        'is_active' => true,
    ]);

    return $municipality;
}

test('one municipality with an unusable koppeling does not keep the others from getting a deelzaak', function () {
    Exceptions::fake();
    fakeDoorkomstZgwOnMain();
    secondPassingMunicipalityWithUnusableKoppeling();

    // Route 1 crosses the healthy doorkomst municipality, route 2 the one whose
    // koppeling cannot be used.
    $hoofdzaak = multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
        lineGeometry([[0.5, -2.0], [3.5, -2.0]]),
    ]);

    CreateDoorkomstZaken::dispatchSync($hoofdzaak);

    expect(createdDeelzaakZaaktypen())->toBe([doorkomstZaaktypeUrl('dk-m')]);
    Exceptions::assertReported(ZaaktypeConnectionMismatchException::class);
});

/**
 * ---------------------------------------------------------------------------
 * A deelzaak the receiving ZGW API refuses
 * ---------------------------------------------------------------------------
 *
 * A failing deelzaak must not keep the municipalities after it on the route
 * from getting theirs. The job still fails at the end so the missing deelzaak
 * stays visible, and a retry only creates the ones that are still missing.
 */

/** A third passing municipality, north of the diagonal route, on main. */
function thirdPassingMunicipality(): Municipality
{
    $municipality = Municipality::factory()->create([
        'name' => 'Derde doorkomstgemeente',
        'geometry' => multipolygon([[1.5, 5.0], [1.5, 6.0], [2.5, 6.0], [2.5, 5.0], [1.5, 5.0]]),
    ]);

    Zaaktype::factory()->create([
        'municipality_id' => $municipality->id,
        'role' => ZaaktypeRole::Doorkomst,
        'connection' => 'main',
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/dk-m3',
        'is_active' => true,
    ]);

    return $municipality;
}

/**
 * Like fakeDoorkomstZgwOnMain(), except that creating a deelzaak with the given
 * zaaktype answers with a server error for as long as $failing is true.
 */
function fakeDoorkomstZgwOnMainFailingFor(string $zaaktypeUrl, bool &$failing): void
{
    $created = 0;

    Http::fake([
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
        ], 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),
        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) use (&$created, &$failing, $zaaktypeUrl) {
            if ($request->method() === 'POST') {
                if ($failing && ($request->data()['zaaktype'] ?? null) === $zaaktypeUrl) {
                    return Http::response([
                        'type' => 'https://zgw.example.com/ref/fouten/APIException/',
                        'code' => 'error',
                        'title' => 'A server error occurred.',
                        'status' => 500,
                        'detail' => 'A server error occurred.',
                        'instance' => 'urn:uuid:00000000-0000-0000-0000-000000000000',
                    ], 500);
                }

                $created++;

                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-'.$created], 201);
            }

            $slug = basename((string) parse_url($request->url(), PHP_URL_PATH));

            return Http::response(array_merge(deelZaakReadResponse(), [
                'url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/'.$slug,
                'identificatie' => strtoupper($slug),
            ]), 200);
        },
        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);
}

/** The three routes, each crossing one of the three passing municipalities. */
function threeRouteHoofdZaak(): Zaak
{
    secondPassingMunicipality();
    thirdPassingMunicipality();

    return multiRouteHoofdZaak([
        lineGeometry([[0.5, 0.5], [3.5, 3.5]]),
        lineGeometry([[0.5, -2.0], [3.5, -2.0]]),
        lineGeometry([[0.5, 5.5], [3.5, 5.5]]),
    ]);
}

/**
 * The zaaktype url of every deelzaak registered locally for the hoofdzaak,
 * sorted, so a duplicate shows up as a repeated entry.
 *
 * @return list<string>
 */
function localDeelzaakZaaktypen(Zaak $hoofdzaak): array
{
    $urls = Zaak::query()
        ->where('hoofdzaak_id', $hoofdzaak->id)
        ->with('zaaktype')
        ->get()
        ->map(fn (Zaak $zaak) => (string) $zaak->zaaktype?->zgw_zaaktype_url)
        ->all();

    sort($urls);

    return $urls;
}

test('a server error on the first of three municipalities still creates the other two, fails the job, and a retry adds only the missing one', function () {
    Exceptions::fake();
    $failing = true;
    fakeDoorkomstZgwOnMainFailingFor(doorkomstZaaktypeUrl('dk-m'), $failing);
    $hoofdzaak = threeRouteHoofdZaak();

    expect(fn () => CreateDoorkomstZaken::dispatchSync($hoofdzaak))
        ->toThrow(DoorkomstZakenIncompleteException::class);

    // All three were attempted, in route order, and the two after the failing
    // one got their deelzaak.
    expect(collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'POST'
            && parse_url($pair[0]->url(), PHP_URL_PATH) === '/zaken/api/v1/zaken')
        ->map(fn ($pair) => (string) $pair[0]->data()['zaaktype'])
        ->values()
        ->all())->toBe([
            doorkomstZaaktypeUrl('dk-m'),
            doorkomstZaaktypeUrl('dk-m2'),
            doorkomstZaaktypeUrl('dk-m3'),
        ]);
    expect(localDeelzaakZaaktypen($hoofdzaak))->toBe([
        doorkomstZaaktypeUrl('dk-m2'),
        doorkomstZaaktypeUrl('dk-m3'),
    ]);
    Exceptions::assertReported(ApiRequestException::class);

    // The receiving side recovers; the retry creates the missing deelzaak and
    // leaves the two existing ones alone.
    $failing = false;

    CreateDoorkomstZaken::dispatchSync($hoofdzaak->fresh());

    // One more create request over both runs, and it is for the missing one.
    expect(createdDeelzaakZaaktypen())->toBe([
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m2'),
        doorkomstZaaktypeUrl('dk-m3'),
    ]);
    expect(localDeelzaakZaaktypen($hoofdzaak))->toBe([
        doorkomstZaaktypeUrl('dk-m'),
        doorkomstZaaktypeUrl('dk-m2'),
        doorkomstZaaktypeUrl('dk-m3'),
    ]);
});

test('names the municipalities without a deelzaak in the job failure', function () {
    Exceptions::fake();
    $failing = true;
    fakeDoorkomstZgwOnMainFailingFor(doorkomstZaaktypeUrl('dk-m'), $failing);
    $hoofdzaak = threeRouteHoofdZaak();
    $failingBrk = Zaaktype::where('zgw_zaaktype_url', doorkomstZaaktypeUrl('dk-m'))->sole()->municipality->brk_identification;

    try {
        CreateDoorkomstZaken::dispatchSync($hoofdzaak);
        $this->fail('Expected the job to fail.');
    } catch (DoorkomstZakenIncompleteException $e) {
        expect($e->failedMunicipalities)->toBe([$failingBrk])
            ->and($e->zaakId)->toBe($hoofdzaak->id)
            // The public zaaknummer is what zaak:create-doorkomst-zaken asks for.
            ->and($e->getMessage())->toContain("(public id {$hoofdzaak->public_id})")
            ->and($e->getPrevious())->toBeInstanceOf(ApiRequestException::class);
    }
});

test('logs a refused deelzaak with its municipality, connection and error response, and without the request payload', function () {
    Exceptions::fake();
    Log::spy();
    $failing = true;
    fakeDoorkomstZgwOnMainFailingFor(doorkomstZaaktypeUrl('dk-m'), $failing);
    $hoofdzaak = threeRouteHoofdZaak();
    $failingBrk = Zaaktype::where('zgw_zaaktype_url', doorkomstZaaktypeUrl('dk-m'))->sole()->municipality->brk_identification;

    try {
        CreateDoorkomstZaken::dispatchSync($hoofdzaak);
    } catch (DoorkomstZakenIncompleteException) {
        // expected
    }

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) use ($hoofdzaak, $failingBrk): bool {
            if (! str_starts_with($message, 'CreateDoorkomstZaken: failed to create the deelzaak')) {
                return false;
            }

            return $context['zaak_id'] === $hoofdzaak->id
                && $context['municipality'] === $failingBrk
                && $context['connection'] === 'main'
                && $context['zaak_url'] === null
                && $context['exception'] === ApiRequestException::class
                && $context['response']['status'] === 500
                && $context['response']['detail'] === 'A server error occurred.'
                // Nothing of what was sent ends up in the line.
                && ! str_contains(json_encode($context), 'Hoofdzaak')
                && ! str_contains(json_encode($context), '123456789');
        })
        ->once();
});

test('records the deelzaak as a route passage and writes that as its aanvraagtype, not the one of the hoofdzaak', function () {
    $eigenschapUrl = ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/aanvraag-type';

    Http::fake([
        // The hoofdzaak carries the aanvraagtype eigenschap of a vergunning.
        OWN_HOST.'/zaken/api/v1/zaken/hoofd-1*' => Http::response([
            'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
            'zaaktype' => OWN_HOST.'/catalogi/api/v1/zaaktypen/hoofd',
            'identificatie' => 'HOOFD-1',
            'bronorganisatie' => '123456789',
            'startdatum' => '2026-07-01',
            'omschrijving' => 'Hoofdzaak',
            '_expand' => [
                'eigenschappen' => [[
                    'url' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1/zaakeigenschappen/1',
                    'uuid' => 'eig-1',
                    'zaak' => OWN_HOST.'/zaken/api/v1/zaken/hoofd-1',
                    'eigenschap' => OWN_HOST.'/catalogi/api/v1/eigenschappen/aanvraag-type',
                    'naam' => 'aanvraag_type',
                    'waarde' => 'Vergunning',
                ]],
            ],
        ], 200),
        OWN_HOST.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(ZgwHttpFake::envelope([]), 200),

        // The catalogus of the doorkomst zaaktype defines the eigenschap too.
        ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen*' => Http::response(ZgwHttpFake::envelope([
            ['url' => $eigenschapUrl, 'naam' => 'aanvraag_type'],
        ]), 200),

        ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken*' => function ($request) {
            if ($request->method() === 'POST') {
                return Http::response(['url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/deel-1'], 201);
            }

            return Http::response(deelZaakReadResponse(), 200);
        },

        '*/catalogi/api/v1/*' => Http::response(ZgwHttpFake::envelope([]), 200),
        '*' => Http::response([], 200),
    ]);

    $scenario = doorkomstScenario(hoofdOwnInstance: true);
    $scenario['hoofdzaak']->update(['role' => ZaaktypeRole::Vergunning]);
    withPassingDoorkomstZaaktype($scenario['passing']);

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']->refresh());

    $deel = Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->firstOrFail();

    expect($deel->role)->toBe(ZaaktypeRole::Doorkomst);

    $written = Http::recorded(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/zaakeigenschappen')
        && $request['eigenschap'] === $eigenschapUrl);

    expect($written)->toHaveCount(1)
        ->and($written->first()[0]['waarde'])->toBe('Doorkomst');
});
