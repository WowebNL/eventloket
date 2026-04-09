<?php

use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Woweb\Openzaak\ObjectsApi;
use Woweb\Openzaak\Openzaak;

const OBJECTS_API_BASE = 'https://objects-api.example.com';
const ZGW_BASE = 'https://zgw.example.com';

// Helper to build a minimal ZGW zaak response
function buildZaakResponse(string $zaakUuid, string $zaaktypeUrl, array $overrides = []): array
{
    return array_merge([
        'url' => ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid,
        'uuid' => $zaakUuid,
        'identificatie' => 'ZAAK-'.$zaakUuid,
        'omschrijving' => 'Test evenementenvergunning',
        'zaaktype' => $zaaktypeUrl,
        'startdatum' => '2026-06-01',
        'registratiedatum' => '2026-05-01',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '001234567',
        'zaakgeometrie' => ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]],
        '_expand' => [
            'zaakobjecten' => [
                [
                    'object' => OBJECTS_API_BASE.'/api/v2/objects/form-obj-1',
                    'objectType' => 'overige',
                    'relatieomschrijving' => '',
                ],
            ],
            'eigenschappen' => [
                ['url' => ZGW_BASE.'/zaken/api/v1/zaakeigenschappen/1', 'uuid' => '1', 'naam' => 'start_evenement', 'waarde' => '2026-06-01T10:00:00+02:00', 'eigenschap' => ZGW_BASE.'/catalogi/api/v1/eigenschappen/1', 'zaak' => ZGW_BASE.'/zaken/api/v1/zaken/zaak-1'],
                ['url' => ZGW_BASE.'/zaken/api/v1/zaakeigenschappen/2', 'uuid' => '2', 'naam' => 'eind_evenement', 'waarde' => '2026-06-01T22:00:00+02:00', 'eigenschap' => ZGW_BASE.'/catalogi/api/v1/eigenschappen/2', 'zaak' => ZGW_BASE.'/zaken/api/v1/zaken/zaak-1'],
            ],
            'rollen' => [
                [
                    'url' => ZGW_BASE.'/zaken/api/v1/rollen/1',
                    'uuid' => '1',
                    'betrokkeneType' => 'natuurlijk_persoon',
                    'roltype' => ZGW_BASE.'/catalogi/api/v1/roltypen/1',
                    'omschrijving' => 'Initiator',
                    'omschrijvingGeneriek' => 'initiator',
                    'contactpersoonRol' => [],
                    'betrokkeneIdentificatie' => [
                        'voornamen' => 'Jan',
                        'geslachtsnaam' => 'Jansen',
                    ],
                ],
            ],
        ],
    ], $overrides);
}

// Helper to build a minimal FormSubmissionObject response (Objects API)
function buildFormSubmissionObjectResponse(string|array|null $lineData = null): array
{
    $eventLocation = [];
    if ($lineData !== null) {
        $eventLocation['line'] = $lineData;
    }

    return [
        'uuid' => 'form-obj-1',
        'type' => OBJECTS_API_BASE.'/api/v2/objecttypes/1',
        'record' => [
            'data' => [
                'eventloket_organisation_uuid' => '00000000-0000-0000-0000-000000000001',
                'eventloket_user_uuid' => '00000000-0000-0000-0000-000000000002',
                'initiator' => [],
                'event_location' => $eventLocation,
                'zaakeigenschappen' => null,
            ],
        ],
    ];
}

beforeEach(function () {
    Config::set('openzaak.url', ZGW_BASE.'/');
    Config::set('openzaak.objectsapi.url', OBJECTS_API_BASE.'/');
    Config::set('app.name', 'eventloket');
});

// ==========================================
// Early return tests
// ==========================================

test('does nothing when zaaktype has triggers_route_check disabled', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => false,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse(['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]]), 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('does nothing when zaaktype is not found or inactive', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/not-found';
    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse(), 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('does nothing when event_location has no line', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse(null), 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('does nothing when event_location line is None', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse('None'), 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('does nothing when line intersects no municipalities', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    // No municipalities in DB, so no intersections
    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('skips passing municipality without doorkomst_zaaktype_id configured', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    // Passing municipality without doorkomst zaaktype
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

// ==========================================
// Happy path
// ==========================================

test('creates one deelzaak for a single passing municipality with doorkomst zaaktype', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => false,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    // Line starts in GM001 [−1..1], crosses GM002 [2..4], ends in GM003 [5..7]
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    $newZaakResponse = buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, [
        'url' => $newZaakUrl,
    ]);

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response($newZaakResponse, 201),
        $newZaakUrl.'*' => Http::response($newZaakResponse, 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(1);
    $zaak = Zaak::first();
    expect($zaak->zgw_zaak_url)->toBe($newZaakUrl);
    expect($zaak->zaaktype_id)->toBe($doorkomstZaaktype->id);
});

test('creates deelzaken for each passing municipality that has a doorkomst zaaktype', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl1 = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $doorkomstZaaktypeUrl2 = ZGW_BASE.'/catalogi/api/v1/zaaktypen/3';
    $newZaakUuid1 = 'new-zaak-1';
    $newZaakUuid2 = 'new-zaak-2';
    $newZaakUrl1 = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid1;
    $newZaakUrl2 = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid2;

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);

    $doorkomstZaaktype1 = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl1,
        'is_active' => true,
    ]);
    $doorkomstZaaktype2 = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl2,
        'is_active' => true,
    ]);

    // Start municipality (no doorkomst zaaktype)
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    // Two passing municipalities
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype1->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype2->id,
    ]);
    // End municipality (no doorkomst zaaktype)
    Municipality::factory()->create([
        'brk_identification' => 'GM004',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[8,-1],[10,-1],[10,1],[8,1],[8,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    // Line starts in GM001, crosses GM002, GM003, ends in GM004
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [9, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::sequence()
            ->push(buildZaakResponse($newZaakUuid1, $doorkomstZaaktypeUrl1, ['url' => $newZaakUrl1]), 201)
            ->push(buildZaakResponse($newZaakUuid2, $doorkomstZaaktypeUrl2, ['url' => $newZaakUrl2]), 201),
        $newZaakUrl1.'*' => Http::response(buildZaakResponse($newZaakUuid1, $doorkomstZaaktypeUrl1, ['url' => $newZaakUrl1]), 200),
        $newZaakUrl2.'*' => Http::response(buildZaakResponse($newZaakUuid2, $doorkomstZaaktypeUrl2, ['url' => $newZaakUrl2]), 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(2);
});

test('deelzaak ZGW store call contains hoofdzaak url of original zaak', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-main';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl, ['url' => $zaakUrl]), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl, 'hoofdzaak' => $zaakUrl]), 201),
        $newZaakUrl.'*' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaken') && $request->method() === 'POST'
        && $request->data()['hoofdzaak'] === $zaakUrl
    );
});

test('copies documents from original zaak to deelzaak', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;
    $documentUrl = ZGW_BASE.'/documenten/api/v1/enkelvoudiginformatieobjecten/doc-1';

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 201),
        $newZaakUrl.'*' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::sequence()
            ->push([
                ['url' => ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten/1', 'zaak' => $zaakUrl, 'informatieobject' => $documentUrl],
            ], 200)
            ->push([], 201), // second call is the store for the deelzaak
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaakinformatieobjecten') && $request->method() === 'POST'
        && $request->data()['informatieobject'] === $documentUrl
        && $request->data()['zaak'] === $newZaakUrl
    );
});

test('local Zaak record is created with correct zaaktype for the deelzaak', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    $newZaakResponse = buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]);

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response($newZaakResponse, 201),
        $newZaakUrl.'*' => Http::response($newZaakResponse, 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    $deelzaak = Zaak::first();
    expect($deelzaak)->not->toBeNull();
    expect($deelzaak->zgw_zaak_url)->toBe($newZaakUrl);
    expect($deelzaak->zaaktype_id)->toBe($doorkomstZaaktype->id);
});

test('line only within start and end municipality produces no deelzaken', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    // Start municipality
    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    // End municipality
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    // Line starts inside GM001, ends inside GM002, no intermediate municipalities
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [3, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
});

test('initiator rol is stored using roltype from doorkomst zaaktype catalogi', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;
    $doorkomstInitiatorRoltypeUrl = ZGW_BASE.'/catalogi/api/v1/roltypen/doorkomst-initiator';

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 201),
        $newZaakUrl.'*' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/catalogi/api/v1/roltypen*' => Http::response(['results' => [
            ['url' => $doorkomstInitiatorRoltypeUrl, 'omschrijvingGeneriek' => 'initiator', 'omschrijving' => 'Initiator', 'zaaktype' => $doorkomstZaaktypeUrl],
        ], 'count' => 1, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/rollen') && $request->method() === 'POST'
        && $request->data()['roltype'] === $doorkomstInitiatorRoltypeUrl
    );
});

test('skips creating deelzaak when one with the same zaaktype already exists for the hoofdzaak', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-1';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $existingDeelzaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/existing-deelzaak';
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    // Zaak response with an existing deelzaak of the same zaaktype in _expand.deelzaken
    $zaakResponseWithDeelzaak = buildZaakResponse($zaakUuid, $zaaktypeUrl, [
        '_expand' => array_merge(buildZaakResponse($zaakUuid, $zaaktypeUrl)['_expand'], [
            'deelzaken' => [
                ['url' => $existingDeelzaakUrl, 'zaaktype' => $doorkomstZaaktypeUrl, 'uuid' => 'existing-deelzaak'],
            ],
        ]),
    ]);

    Http::fake([
        $zaakUrl.'*' => Http::response($zaakResponseWithDeelzaak, 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    expect(Zaak::count())->toBe(0);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/zaken') && $request->method() === 'POST');
});

test('creates initial status using the statustype with the lowest volgnummer', function () {
    $zaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/1';
    $doorkomstZaaktypeUrl = ZGW_BASE.'/catalogi/api/v1/zaaktypen/2';
    $newZaakUuid = 'new-zaak-1';
    $newZaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$newZaakUuid;
    $initiaalStatustypeUrl = ZGW_BASE.'/catalogi/api/v1/statustypen/10';

    Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $zaaktypeUrl,
        'is_active' => true,
        'triggers_route_check' => true,
    ]);
    $doorkomstZaaktype = Zaaktype::factory()->create([
        'zgw_zaaktype_url' => $doorkomstZaaktypeUrl,
        'is_active' => true,
    ]);

    Municipality::factory()->create([
        'brk_identification' => 'GM001',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM002',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
        'doorkomst_zaaktype_id' => $doorkomstZaaktype->id,
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM003',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
        'doorkomst_zaaktype_id' => null,
    ]);

    $zaakUuid = 'zaak-main';
    $zaakUrl = ZGW_BASE.'/zaken/api/v1/zaken/'.$zaakUuid;
    $lineGeoJson = ['type' => 'LineString', 'coordinates' => [[0, 0], [6, 0]]];

    Http::fake([
        $zaakUrl.'*' => Http::response(buildZaakResponse($zaakUuid, $zaaktypeUrl, ['url' => $zaakUrl]), 200),
        OBJECTS_API_BASE.'*' => Http::response(buildFormSubmissionObjectResponse($lineGeoJson), 200),
        ZGW_BASE.'/zaken/api/v1/zaken' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 201),
        $newZaakUrl.'*' => Http::response(buildZaakResponse($newZaakUuid, $doorkomstZaaktypeUrl, ['url' => $newZaakUrl]), 200),
        ZGW_BASE.'/catalogi/api/v1/eigenschappen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/catalogi/api/v1/roltypen*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/zaken/api/v1/zaakinformatieobjecten*' => Http::response(['results' => [], 'count' => 0, 'next' => null], 200),
        ZGW_BASE.'/catalogi/api/v1/statustypen*' => Http::response([
            'results' => [
                ['url' => ZGW_BASE.'/catalogi/api/v1/statustypen/11', 'zaaktype' => $doorkomstZaaktypeUrl, 'omschrijving' => 'Afgehandeld', 'volgnummer' => 3, 'isEindstatus' => true],
                ['url' => $initiaalStatustypeUrl, 'zaaktype' => $doorkomstZaaktypeUrl, 'omschrijving' => 'Ontvangen', 'volgnummer' => 1, 'isEindstatus' => false],
                ['url' => ZGW_BASE.'/catalogi/api/v1/statustypen/12', 'zaaktype' => $doorkomstZaaktypeUrl, 'omschrijving' => 'In behandeling', 'volgnummer' => 2, 'isEindstatus' => false],
            ],
            'count' => 3,
            'next' => null,
        ], 200),
        ZGW_BASE.'/zaken/api/v1/statussen' => Http::response(['url' => ZGW_BASE.'/zaken/api/v1/statussen/1'], 201),
        ZGW_BASE.'*' => Http::response([], 200),
    ]);

    (new CreateDoorkomstZaken($zaakUrl))->handle(app(Openzaak::class), app(ObjectsApi::class));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/zaken/api/v1/statussen')
        && $request->method() === 'POST'
        && $request->data()['zaak'] === $newZaakUrl
        && $request->data()['statustype'] === $initiaalStatustypeUrl
    );
});
