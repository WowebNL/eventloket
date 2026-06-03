<?php

declare(strict_types=1);

/**
 * EventLocationGeometryBuilder converteert de Map-state uit het
 * formulier naar een GeoJSON-string voor ZGW. De Map-component schrijft
 * z'n state als `{lat, lng, geojson: FeatureCollection}` — verpakt in
 * een Repeater dus `[{naam, buitenLocatieVanHetEvenement: {...}}, ...]`.
 *
 * De opdrachtgever rapporteerde een "Missing or malformed type"-fout op
 * deze stap; deze tests zorgen dat we exact dezelfde structuur als
 * dotswan/filament-map-picker bouwt door de pipeline kunnen halen.
 */

use App\EventForm\Submit\EventLocationGeometryBuilder;
use App\Services\LocatieserverService;

beforeEach(function () {
    // De service maakt buiten BAG-adresconversie geen externe calls aan;
    // een stub volstaat voor polygoon/lijn-tests.
    $this->builder = new EventLocationGeometryBuilder(
        $this->mock(LocatieserverService::class)
    );
});

test('één polygon op de Locatie-kaart → één Polygon in de GeoJSON', function () {
    $eventLocation = [
        'multipolygons' => [
            [
                'naamVanDeLocatieKaart' => 'Plein',
                'buitenLocatieVanHetEvenement' => [
                    'lat' => 50.85,
                    'lng' => 5.69,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => new stdClass,
                                'geometry' => [
                                    'type' => 'Polygon',
                                    'coordinates' => [[
                                        [5.69, 50.85],
                                        [5.70, 50.85],
                                        [5.70, 50.86],
                                        [5.69, 50.86],
                                        [5.69, 50.85],
                                    ]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $geojson = $this->builder->buildGeoJson($eventLocation);

    expect($geojson)->toBeString()
        ->and($geojson)->toContain('Polygon')
        ->and($geojson)->toContain('GeometryCollection');
});

test('lijn-route uit de routesOpKaart → LineString in de GeoJSON', function () {
    $eventLocation = [
        'line' => [
            [
                'routeVanHetEvenement' => [
                    'lat' => 50.85,
                    'lng' => 5.69,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [
                            [
                                'type' => 'Feature',
                                'properties' => new stdClass,
                                'geometry' => [
                                    'type' => 'LineString',
                                    'coordinates' => [
                                        [5.69, 50.85],
                                        [5.71, 50.86],
                                        [5.73, 50.87],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $geojson = $this->builder->buildGeoJson($eventLocation);

    expect($geojson)->toBeString()
        ->and($geojson)->toContain('LineString');
});

test('lege Map-state (geen tekening gemaakt) → null, geen exception', function () {
    $eventLocation = [
        'multipolygons' => [
            [
                'naamVanDeLocatieKaart' => 'Plein',
                'buitenLocatieVanHetEvenement' => [
                    'lat' => 50.85,
                    'lng' => 5.69,
                    'geojson' => [
                        'type' => 'FeatureCollection',
                        'features' => [], // niets ingetekend
                    ],
                ],
            ],
        ],
    ];

    expect($this->builder->buildGeoJson($eventLocation))->toBeNull();
});

test('volledig leeg event-location → null', function () {
    expect($this->builder->buildGeoJson([]))->toBeNull();
});

test('nieuwe shape: één Map met meerdere polygonen → meerdere Polygons in de GeoJSON', function () {
    // Repeater-eruit: multipolygons is nu één Map-state met N features.
    $eventLocation = [
        'multipolygons' => [
            'lat' => 50.85,
            'lng' => 5.69,
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[5.69, 50.85], [5.70, 50.85], [5.70, 50.86], [5.69, 50.86], [5.69, 50.85]]],
                        ],
                    ],
                    [
                        'type' => 'Feature',
                        'properties' => new stdClass,
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[5.71, 50.85], [5.72, 50.85], [5.72, 50.86], [5.71, 50.86], [5.71, 50.85]]],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $geojson = $this->builder->buildGeoJson($eventLocation);

    expect($geojson)->toBeString()
        ->and($geojson)->toContain('GeometryCollection');

    // Beide polygons moeten in de output zitten — substring count op
    // "Polygon" geeft minstens 2 + 1 (de outer GeometryCollection-naam).
    $decoded = json_decode((string) $geojson, true);
    expect($decoded['geometries'])->toHaveCount(2)
        ->and($decoded['geometries'][0]['type'])->toBe('Polygon')
        ->and($decoded['geometries'][1]['type'])->toBe('Polygon');
});
