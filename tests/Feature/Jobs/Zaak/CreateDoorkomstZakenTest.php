<?php

declare(strict_types=1);

/**
 * Tests for CreateDoorkomstZaken.
 *
 * A route event can have more than one route drawn on the map. Every
 * drawn route has to produce doorkomst deelzaken for the municipalities
 * it crosses. The result is the union over all routes, minus the start
 * and end municipalities of all routes together: a municipality where any
 * route begins or ends never gets a deelzaak, not even when another route
 * merely passes through it.
 *
 * The fixture is a grid of one-degree municipality squares:
 *
 *   y  2..3   GM-B1   GM-B2   GM-B3
 *   y  1..2   GM-C1
 *   y  0..1   GM-A1   GM-A2   GM-A3
 *   y -1..0   GM-A0
 *             x 0..1  x 1..2  x 2..3
 *
 * Route A runs west to east through row A, route B does the same through
 * row B. Both routes start and end in an outer square, so the expected
 * result is one deelzaak for GM-A2 and one for GM-B2. GM-A0 and GM-C1 sit
 * in the first column and are only touched by the north-south route used
 * for the global-exclusion case.
 *
 * The hoofdzaak itself belongs to GM-HOOFD, which lies outside the grid.
 */

use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Jobs\Zaak\CreateDoorkomstZaken;
use App\Models\Municipality;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;
use Woweb\Openzaak\Openzaak;

uses(RefreshDatabase::class);

/**
 * A municipality square with its own active doorkomst zaaktype.
 */
function doorkomstMunicipality(string $brk, float $minX, float $minY, float $maxX, float $maxY, bool $withDoorkomstZaaktype = true): Municipality
{
    $municipality = Municipality::factory()->create([
        'name' => $brk,
        'brk_identification' => $brk,
        'geometry' => json_encode([
            'type' => 'MultiPolygon',
            'coordinates' => [[[
                [$minX, $minY],
                [$maxX, $minY],
                [$maxX, $maxY],
                [$minX, $maxY],
                [$minX, $minY],
            ]]],
        ]),
    ]);

    if ($withDoorkomstZaaktype) {
        $zaaktype = Zaaktype::factory()->create([
            'name' => 'Doorkomst '.$brk,
            'municipality_id' => $municipality->id,
            'is_active' => true,
            'triggers_route_check' => false,
            'zgw_zaaktype_url' => doorkomstZaaktypeUrl($brk),
        ]);

        $municipality->doorkomst_zaaktype_id = $zaaktype->id;
        $municipality->save();
    }

    return $municipality->refresh();
}

function doorkomstZaaktypeUrl(string $brk): string
{
    return ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/doorkomst-'.$brk;
}

/**
 * The grid described in the file docblock.
 *
 * @return array<string, Municipality>
 */
function doorkomstGrid(): array
{
    return [
        'GM-A0' => doorkomstMunicipality('GM-A0', 0, -1, 1, 0),
        'GM-A1' => doorkomstMunicipality('GM-A1', 0, 0, 1, 1),
        'GM-A2' => doorkomstMunicipality('GM-A2', 1, 0, 2, 1),
        'GM-A3' => doorkomstMunicipality('GM-A3', 2, 0, 3, 1),
        'GM-C1' => doorkomstMunicipality('GM-C1', 0, 1, 1, 2),
        'GM-B1' => doorkomstMunicipality('GM-B1', 0, 2, 1, 3),
        'GM-B2' => doorkomstMunicipality('GM-B2', 1, 2, 2, 3),
        'GM-B3' => doorkomstMunicipality('GM-B3', 2, 2, 3, 3),
    ];
}

/**
 * A route-check zaak whose form state holds the given map state.
 */
function doorkomstHoofdZaak(mixed $routeState): Zaak
{
    $municipality = doorkomstMunicipality('GM-HOOFD', 10, 10, 11, 11, false);

    $zaaktype = Zaaktype::factory()->create([
        'name' => 'Evenementenvergunning',
        'municipality_id' => $municipality->id,
        'is_active' => true,
        'triggers_route_check' => true,
        'zgw_zaaktype_url' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
    ]);

    return Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'zgw_zaak_url' => ZgwHttpFake::$baseUrl.'/zaken/api/v1/zaken/hoofdzaak',
        'form_state_snapshot' => ['values' => ['routesOpKaart' => $routeState]],
    ]);
}

/**
 * @param  list<mixed>  $coordinates
 * @return array<string, mixed>
 */
function doorkomstFeature(array $coordinates, string $type = 'LineString'): array
{
    return [
        'type' => 'Feature',
        'properties' => [],
        'geometry' => [
            'type' => $type,
            'coordinates' => $coordinates,
        ],
    ];
}

/**
 * Current map state: a single Map component holding N features.
 *
 * @param  list<array<string, mixed>>  $features
 * @return array<string, mixed>
 */
function doorkomstMapState(array $features): array
{
    return [
        'lat' => 0.5,
        'lng' => 0.5,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => $features,
        ],
    ];
}

/** @return list<list<float>> */
function doorkomstRouteThroughRowA(): array
{
    return [[0.5, 0.5], [2.5, 0.5]];
}

/** @return list<list<float>> */
function doorkomstRouteThroughRowB(): array
{
    return [[0.5, 2.5], [2.5, 2.5]];
}

/**
 * South to north through the first column: starts in GM-A0, ends in
 * GM-B1 and passes straight through GM-A1 and GM-C1.
 *
 * @return list<list<float>>
 */
function doorkomstRouteThroughFirstColumn(): array
{
    return [[0.5, -0.5], [0.5, 2.5]];
}

/**
 * Minimal ZGW responses: creating a deelzaak succeeds, every catalogi
 * lookup comes back empty so the job only exercises the route logic.
 */
function doorkomstFakeZgw(): void
{
    $base = ZgwHttpFake::$baseUrl;
    $created = 0;

    Http::fake(function (Request $request) use ($base, &$created) {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && $path === '/zaken/api/v1/zaken') {
            $created++;

            return Http::response(['url' => $base.'/zaken/api/v1/zaken/deelzaak-'.$created], 201);
        }

        if ($request->method() === 'GET' && str_starts_with($path, '/zaken/api/v1/zaken/')) {
            return Http::response(doorkomstZaakPayload($base.$path), 200);
        }

        return Http::response([], 200);
    });
}

/**
 * @return array<string, mixed>
 */
function doorkomstZaakPayload(string $url): array
{
    return [
        'url' => $url,
        'identificatie' => 'ZAAK-'.basename($url),
        'zaaktype' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/zaaktypen/hoofd',
        'omschrijving' => 'Route event',
        'startdatum' => '2026-09-01',
        'registratiedatum' => '2026-08-19',
        'einddatum' => null,
        'einddatumGepland' => null,
        'uiterlijkeEinddatumAfdoening' => null,
        'bronorganisatie' => '123456782',
        'zaakgeometrie' => null,
        '_expand' => [
            'deelzaken' => [],
            'eigenschappen' => [
                doorkomstEigenschap($url, 'start_evenement', '2026-09-01T10:00:00+02:00'),
                doorkomstEigenschap($url, 'eind_evenement', '2026-09-01T18:00:00+02:00'),
            ],
        ],
    ];
}

/**
 * @return array<string, string>
 */
function doorkomstEigenschap(string $zaakUrl, string $naam, string $waarde): array
{
    return [
        'uuid' => $naam,
        'url' => $zaakUrl.'/zaakeigenschappen/'.$naam,
        'zaak' => $zaakUrl,
        'eigenschap' => ZgwHttpFake::$baseUrl.'/catalogi/api/v1/eigenschappen/'.$naam,
        'naam' => $naam,
        'waarde' => $waarde,
    ];
}

/**
 * The zaaktype URLs the job actually created a deelzaak for.
 *
 * @return list<string>
 */
function doorkomstCreatedZaaktypen(): array
{
    $zaaktypen = [];

    foreach (Http::recorded() as [$request, $response]) {
        if ($request->method() !== 'POST') {
            continue;
        }
        if (parse_url($request->url(), PHP_URL_PATH) !== '/zaken/api/v1/zaken') {
            continue;
        }
        $zaaktypen[] = (string) ($request->data()['zaaktype'] ?? '');
    }

    sort($zaaktypen);

    return $zaaktypen;
}

function doorkomstRunJob(Zaak $zaak): void
{
    (new CreateDoorkomstZaken($zaak))->handle(app(Openzaak::class), new ZaakeigenschappenMap);
}

beforeEach(function () {
    // Running with PostGIS: the extension is needed for the intersect check.
    if (config('database.default') === 'pgsql') {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');
        } catch (Exception) {
            // Already present, or created by the container init script.
        }
    }

    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    doorkomstGrid();
    doorkomstFakeZgw();
});

test('creates doorkomst zaken for the crossed municipalities of every drawn route', function () {
    $zaak = doorkomstHoofdZaak(doorkomstMapState([
        doorkomstFeature(doorkomstRouteThroughRowA()),
        doorkomstFeature(doorkomstRouteThroughRowB()),
    ]));

    doorkomstRunJob($zaak);

    expect(doorkomstCreatedZaaktypen())->toBe([
        doorkomstZaaktypeUrl('GM-A2'),
        doorkomstZaaktypeUrl('GM-B2'),
    ]);
});

test('excludes the start and end municipality of every route', function () {
    $zaak = doorkomstHoofdZaak(doorkomstMapState([
        doorkomstFeature(doorkomstRouteThroughRowA()),
        doorkomstFeature(doorkomstRouteThroughRowB()),
    ]));

    doorkomstRunJob($zaak);

    $created = doorkomstCreatedZaaktypen();

    foreach (['GM-A1', 'GM-A3', 'GM-B1', 'GM-B3'] as $brk) {
        expect($created)->not->toContain(doorkomstZaaktypeUrl($brk));
    }
});

test('never creates a deelzaak for a municipality another route starts in', function () {
    // GM-A1 is the start municipality of the route through row A, and the
    // route through the first column passes straight through it. It is a
    // start municipality of this event, so it gets no deelzaak at all.
    $zaak = doorkomstHoofdZaak(doorkomstMapState([
        doorkomstFeature(doorkomstRouteThroughRowA()),
        doorkomstFeature(doorkomstRouteThroughFirstColumn()),
    ]));

    doorkomstRunJob($zaak);

    $created = doorkomstCreatedZaaktypen();

    expect($created)->not->toContain(doorkomstZaaktypeUrl('GM-A1'))
        ->and($created)->toBe([
            doorkomstZaaktypeUrl('GM-A2'),
            doorkomstZaaktypeUrl('GM-C1'),
        ]);
});

test('reads a MultiLineString route instead of crashing on it', function () {
    $zaak = doorkomstHoofdZaak(doorkomstMapState([
        doorkomstFeature([
            doorkomstRouteThroughRowA(),
            doorkomstRouteThroughRowB(),
        ], 'MultiLineString'),
    ]));

    doorkomstRunJob($zaak);

    expect(doorkomstCreatedZaaktypen())->toBe([
        doorkomstZaaktypeUrl('GM-A2'),
        doorkomstZaaktypeUrl('GM-B2'),
    ]);
});

test('reads every route from an old repeater draft state', function () {
    $zaak = doorkomstHoofdZaak([
        ['routeVanHetEvenement' => doorkomstMapState([doorkomstFeature(doorkomstRouteThroughRowA())])],
        ['routeVanHetEvenement' => doorkomstMapState([doorkomstFeature(doorkomstRouteThroughRowB())])],
    ]);

    doorkomstRunJob($zaak);

    expect(doorkomstCreatedZaaktypen())->toBe([
        doorkomstZaaktypeUrl('GM-A2'),
        doorkomstZaaktypeUrl('GM-B2'),
    ]);
});
