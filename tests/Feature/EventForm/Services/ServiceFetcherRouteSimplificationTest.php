<?php

declare(strict_types=1);

/**
 * The route has two representations, and they must not be mixed up.
 *
 * What is shown, stored and handed to the case system is the full route. What
 * the municipality check runs `intersects` over is a reduced copy, because
 * that loop runs once per municipality, synchronously, on every change to a
 * location field, and a recorded route carries thousands of positions.
 *
 * Both directions are failures: the reduced line becoming the stored route,
 * and the full line reaching the `intersects` loop.
 */

use App\EventForm\Services\LocationServerCheckService;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Submit\ZaakeigenschappenMap;
use App\Models\Municipality;
use App\Services\LocatieserverService;
use App\Support\Geo\LineSimplifier;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\RecordingLocationServerCheckService;

/**
 * A dense route crossing the test municipality from west to east.
 *
 * @return list<array{0: float, 1: float}>
 */
function simplificationDensePositions(int $count, float $fromLongitude = 2.5, float $toLongitude = 3.5): array
{
    $positions = [];

    for ($index = 0; $index < $count; $index++) {
        $positions[] = [
            $fromLongitude + ($toLongitude - $fromLongitude) * $index / ($count - 1),
            sin($index / 50) * 0.01,
        ];
    }

    return $positions;
}

/**
 * Map state as the route field holds it.
 *
 * @param  list<array{0: float, 1: float}>  $positions
 * @return array<string, mixed>
 */
function simplificationRouteMapState(array $positions): array
{
    return [
        'lat' => 0.0,
        'lng' => 3.0,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => new stdClass,
                'geometry' => ['type' => 'LineString', 'coordinates' => $positions],
            ]],
        ],
    ];
}

/**
 * The shape a draft from before the route repeater was replaced still carries.
 *
 * @param  list<array{0: float, 1: float}>  $positions
 * @return array<string, mixed>
 */
function simplificationLegacyRouteRow(array $positions): array
{
    return ['row-1' => ['routeVanHetEvenement' => simplificationRouteMapState($positions)]];
}

beforeEach(function () {
    Http::fake();

    Municipality::factory()->create([
        'brk_identification' => 'GM0002',
        'name' => 'RouteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);

    $this->recorder = new RecordingLocationServerCheckService(new LocatieserverService);
    app()->instance(LocationServerCheckService::class, $this->recorder);

    $this->fetcher = app(ServiceFetcher::class);
});

test('the full line never reaches the intersects loop', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationRouteMapState(simplificationDensePositions(3000)),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect($this->recorder->inputs)->toHaveCount(1);

    expect(count($this->recorder->lastLinePositions()))
        ->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS)
        ->toBeGreaterThan(2);
});

test('the reduced line never becomes the stored route', function () {
    $positions = simplificationDensePositions(3000);

    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationRouteMapState($positions),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    $stored = $state->get('routesOpKaart')['geojson']['features'][0]['geometry']['coordinates'];

    expect($stored)->toHaveCount(3000)
        ->and($stored)->toBe($positions);
});

test('the case system is handed the full line, not the reduced one', function () {
    $positions = simplificationDensePositions(3000);

    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationRouteMapState($positions),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    $line = (new ZaakeigenschappenMap)->buildEventLocation($state)['line'];

    $handedOn = $line['geojson']['features'][0]['geometry']['coordinates'];

    expect($handedOn)->toHaveCount(3000)
        ->and($handedOn)->toBe($positions)
        ->and(count($handedOn))->toBeGreaterThan(count($this->recorder->lastLinePositions()));
});

test('the form and the case system read the same route', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationRouteMapState(simplificationDensePositions(3000)),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    $found = collect($state->get('inGemeentenResponse.all.items'))->pluck('brk_identification')->all();

    $line = (new ZaakeigenschappenMap)->buildEventLocation($state)['line'];

    expect($found)->toBe(['GM0002'])
        ->and($line)->toBe($state->get('routesOpKaart'));
});

test('a changed route busts the cache and is checked again', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationRouteMapState(simplificationDensePositions(3000)),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);
    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect($this->recorder->inputs)->toHaveCount(1);

    $state->setField('routesOpKaart', simplificationRouteMapState(simplificationDensePositions(3000, 2.6, 3.4)));

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect($this->recorder->inputs)->toHaveCount(2)
        ->and($this->recorder->inputs[0]->lines)->not->toBe($this->recorder->inputs[1]->lines);
});

test('a route kept in the older draft shape is reduced the same way', function () {
    // Drafts made before the route repeater was replaced by one map still hold
    // the route one level deeper. The reduction has to reach those too, or an
    // old draft puts the full line straight into the `intersects` loop.
    $positions = simplificationDensePositions(3000);

    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => simplificationLegacyRouteRow($positions),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect(count($this->recorder->lastLinePositions()))
        ->toBeLessThanOrEqual(LineSimplifier::MAX_POSITIONS);

    $stored = $state->get('routesOpKaart')['row-1']['routeVanHetEvenement']['geojson']['features'][0]['geometry']['coordinates'];

    expect($stored)->toBe($positions);
});
