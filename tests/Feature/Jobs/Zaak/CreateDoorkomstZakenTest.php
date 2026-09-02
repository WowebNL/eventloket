<?php

use App\Enums\Role;
use App\Enums\ZaaktypeRole;
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

test('copies documents cross-instance: downloads from the hoofdzaak and re-creates them in the deelzaak instance', function () {
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
        && $request->data()['bronorganisatie'] === '123456789'
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

test('links the existing document url and does not copy when the deelzaak shares the hoofdzaak instance', function () {
    // Hoofdzaak on main; doorkomst zaaktype on main too, so both live in one
    // instance and the document url is directly linkable.
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

test('skips a document cross-instance when no target informatieobjecttype resolves, but still creates the deelzaak and its status', function () {
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

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // The deelzaak is still created locally.
    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);

    // No document was re-created in the target instance.
    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten'));

    // The initial status was still set.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_starts_with($request->url(), ZgwHttpFake::$baseUrl.'/zaken/api/v1/statussen'));
});

test('a failing document copy does not abort the remaining documents or the deelzaak', function () {
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

    CreateDoorkomstZaken::dispatchSync($scenario['hoofdzaak']);

    // Exactly one document was re-created (the second): the failure of the first
    // did not abort the loop.
    $documentenPosts = collect(Http::recorded())
        ->filter(fn ($pair) => $pair[0]->method() === 'POST'
            && str_starts_with($pair[0]->url(), ZgwHttpFake::$baseUrl.'/documenten/api/v1/enkelvoudiginformatieobjecten'))
        ->count();
    expect($documentenPosts)->toBe(1);

    // The deelzaak is still created locally.
    expect(Zaak::where('hoofdzaak_id', $scenario['hoofdzaak']->id)->count())->toBe(1);
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
 */
function fakeDoorkomstWithCatalogusEigenschappen(array $hoofdEigenschappen, array $deelCatalogus): void
{
    $expanded = [];
    foreach ($hoofdEigenschappen as $naam => $waarde) {
        $expanded[] = ['naam' => $naam, 'waarde' => $waarde];
    }

    $catalogus = [];
    foreach ($deelCatalogus as $naam => $slug) {
        $catalogus[] = [
            'url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/'.$slug,
            'naam' => $naam,
        ];
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
