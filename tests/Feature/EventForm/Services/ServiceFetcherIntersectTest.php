<?php

declare(strict_types=1);

/**
 * Live gemeente-detectie wanneer de organisator op de Locatie-stap een
 * polygon (of lijn) tekent. Bug-rapport van de opdrachtgever:
 * "het polygoon op de kaart leidt niet tot een automatische
 * gemeentedetectie."
 *
 * Wat in de pipeline misging: ServiceFetcher::fetchInGemeentenResponse
 * gaf `polygons: null` mee (locatieSOpKaart werd nooit verzameld), en
 * de line-verzameling pakte de hele Map-state-wrapper (`{lat, lng,
 * geojson: ...}`) i.p.v. de losse GeoJSON-geometrie eruit. Beide gaven
 * uiteindelijk geen gemeente-output.
 *
 * Deze tests bewijzen de fix: een ingetekend polygon dat een
 * municipality kruist levert die municipality terug in
 * `inGemeentenResponse.all.items`.
 */

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('ingetekend polygon over een gemeente → gemeente komt in inGemeentenResponse', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM0001',
        'name' => 'Heerlen',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);

    $state = new FormState(values: [
        'locatieSOpKaart' => [
            [
                'naamVanDeLocatieKaart' => 'Plein',
                'buitenLocatieVanHetEvenement' => [
                    'lat' => 0.0,
                    'lng' => 0.0,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => new stdClass,
                                'geometry' => [
                                    'type' => 'Polygon',
                                    'coordinates' => [[
                                        [-0.5, -0.5],
                                        [0.5, -0.5],
                                        [0.5, 0.5],
                                        [-0.5, 0.5],
                                        [-0.5, -0.5],
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    $response = $state->get('inGemeentenResponse');
    expect($response)->toBeArray()
        ->and($response['all']['items'])->toHaveCount(1)
        ->and($response['all']['items'][0]['brk_identification'])->toBe('GM0001');
});

test('ingetekende lijn over twee gemeenten → beide gemeenten komen in response', function () {
    Municipality::factory()->create([
        'brk_identification' => 'GM0001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0002',
        'name' => 'EindGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);

    $state = new FormState(values: [
        'routesOpKaart' => [
            [
                'routeVanHetEvenement' => [
                    'lat' => 0.0,
                    'lng' => 0.0,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => new stdClass,
                                'geometry' => [
                                    'type' => 'LineString',
                                    'coordinates' => [[0, 0], [3, 0]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    $response = $state->get('inGemeentenResponse');
    $brkIds = collect($response['all']['items'])->pluck('brk_identification')->toArray();

    expect($brkIds)->toContain('GM0001')
        ->and($brkIds)->toContain('GM0002');
});

test('nieuwe shape: één Map met meerdere polygonen → alle gemeenten erin', function () {
    // Repeater-eruit-fix: locatieSOpKaart is nu een single Map-state,
    // geen lijst van rijen. De Map kan zelf meerdere features bevatten.
    Municipality::factory()->create([
        'brk_identification' => 'GM0001',
        'name' => 'GemeenteWest',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[-1,-1],[1,-1],[1,1],[-1,1],[-1,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0002',
        'name' => 'GemeenteOost',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[5,-1],[7,-1],[7,1],[5,1],[5,-1]]]]}',
    ]);

    $state = new FormState(values: [
        'locatieSOpKaart' => [
            'lat' => 0.0,
            'lng' => 0.0,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    // Polygon binnen GemeenteWest
                    [
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[-0.5, -0.5], [0.5, -0.5], [0.5, 0.5], [-0.5, 0.5], [-0.5, -0.5]]],
                        ],
                    ],
                    // Polygon binnen GemeenteOost
                    [
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[5.5, -0.5], [6.5, -0.5], [6.5, 0.5], [5.5, 0.5], [5.5, -0.5]]],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    $brkIds = collect($state->get('inGemeentenResponse.all.items'))->pluck('brk_identification')->toArray();
    expect($brkIds)->toContain('GM0001')
        ->and($brkIds)->toContain('GM0002');
});

test('lege locatieSOpKaart en routesOpKaart → geen response, geen exception', function () {
    $state = new FormState(values: [
        'locatieSOpKaart' => [],
        'routesOpKaart' => [],
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    expect($state->get('inGemeentenResponse'))->toBeNull();
});
