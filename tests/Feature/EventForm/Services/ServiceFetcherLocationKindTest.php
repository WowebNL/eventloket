<?php

declare(strict_types=1);

/**
 * Unticking a location kind must take its answer out of the municipality
 * check.
 *
 * Bug report: an organiser copies an application that took place in a
 * building, unticks "in a building", draws a route instead, and the
 * municipality of the copied address is still offered as a choice — the
 * application can end up with the source event's municipality.
 *
 * Cause: unticking hides the field but leaves its raw value in the form
 * state (Filament keeps the value of a hidden component, and
 * `FormState::absorbFields()` merges instead of replaces), and
 * `ServiceFetcher` read the location fields straight from the state
 * without looking at `waarVindtHetEvenementPlaats`.
 */

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->fetcher = app(ServiceFetcher::class);

    Municipality::factory()->create([
        'brk_identification' => 'GM0001',
        'name' => 'BronGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0002',
        'name' => 'RouteGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);

    // Any PDOK lookup would resolve to the source municipality; the copied
    // address must not reach it in the first place.
    Http::fake(['*' => Http::response([
        'response' => ['docs' => [['gemeentecode' => '0001', 'gemeentenaam' => 'BronGemeente']]],
    ])]);
});

/** The address of the copied event, as the auto-fill stored it. */
function gekopieerdAdres(): array
{
    return ['row-1' => [
        'naamVanDeLocatieGebouw' => 'Zaal',
        'adresVanHetGebouwWaarUwEvenementPlaatsvindt1' => [
            'postcode' => '6411AA',
            'huisnummer' => '1',
            'brkGemeente' => 'GM0001',
            'straatnaam' => 'Straat',
            'plaatsnaam' => 'Plaats',
        ],
    ]];
}

/** Map state as the map component writes a route away. */
function routeDoor(array $coordinates): array
{
    return [
        'lat' => 0.0,
        'lng' => 0.0,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => new stdClass,
                'geometry' => ['type' => 'LineString', 'coordinates' => $coordinates],
            ]],
        ],
    ];
}

test('an address left behind by an unticked kind stays out of the municipality check', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'adresVanDeGebouwEn' => gekopieerdAdres(),
        'routesOpKaart' => routeDoor([[2.5, 0.0], [3.5, 0.0]]),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    $brkIds = collect($state->get('inGemeentenResponse.all.items'))->pluck('brk_identification')->all();

    expect($brkIds)->toBe(['GM0002'])
        ->and($state->get('gemeenten'))->not->toHaveKey('GM0001');
});

test('unticking a kind re-runs the check instead of returning the cached response', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['gebouw', 'route'],
        'adresVanDeGebouwEn' => gekopieerdAdres(),
        'routesOpKaart' => routeDoor([[2.5, 0.0], [3.5, 0.0]]),
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect(collect($state->get('inGemeentenResponse.all.items'))->pluck('brk_identification')->sort()->values()->all())
        ->toBe(['GM0001', 'GM0002']);

    // Only the tick boxes change; the address and the route keep the value
    // they had. That must still count as different input, or the fetcher
    // hands back the response that included the address.
    $state->setField('waarVindtHetEvenementPlaats', ['route']);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect(collect($state->get('inGemeentenResponse.all.items'))->pluck('brk_identification')->all())
        ->toBe(['GM0002']);
});

test('the gate drops an earlier response once no ticked kind holds a location', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'adresVanDeGebouwEn' => gekopieerdAdres(),
        // The organiser has not drawn the route yet.
        'routesOpKaart' => [],
        // What the copied application left behind.
        'inGemeentenResponse' => ['all' => [
            'items' => [['brk_identification' => 'GM0001', 'name' => 'BronGemeente']],
            'object' => ['GM0001' => ['brk_identification' => 'GM0001', 'name' => 'BronGemeente']],
            'within' => true,
        ]],
    ]);

    $this->fetcher->fetch('inGemeentenResponse', $state);

    expect($state->get('inGemeentenResponse'))->toBeNull()
        ->and($state->get('gemeenten'))->toBeNull();
});
